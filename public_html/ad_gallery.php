<?php
require_once 'db_config.php';
$conn = getDBConnection();
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) { header('Location: al_login.php'); exit(); }

function ensureGalleryTables($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS gallery_albums (
        id INT PRIMARY KEY AUTO_INCREMENT, title VARCHAR(255) NOT NULL, subtitle TEXT,
        display_order INT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        status ENUM('active','archived') DEFAULT 'active', created_by INT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $conn->query("CREATE TABLE IF NOT EXISTS gallery_images (
        id INT PRIMARY KEY AUTO_INCREMENT, album_id INT NOT NULL, file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL, title VARCHAR(255), description TEXT, display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM gallery_images");
    if ($res) while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    if (!in_array('status', $cols)) $conn->query("ALTER TABLE gallery_images ADD COLUMN status ENUM('active','archived') DEFAULT 'active' AFTER updated_at");
    if (!in_array('is_highlight', $cols)) $conn->query("ALTER TABLE gallery_images ADD COLUMN is_highlight TINYINT(1) DEFAULT 0 AFTER status");
}
ensureGalleryTables($conn);

$upload_dir = str_replace('\\', '/', __DIR__ . '/uploads/gallery/');
if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $a = $_POST['action'] ?? '';
    $out = ['success' => false, 'message' => ''];
    if ($a === 'create_album') {
        $t = trim($_POST['title'] ?? ''); $s = trim($_POST['subtitle'] ?? ''); $o = (int)($_POST['display_order'] ?? 0);
        if ($t === '') { $out['message'] = 'Title is required'; echo json_encode($out); exit; }
        $q = $conn->prepare("INSERT INTO gallery_albums (title,subtitle,display_order,created_by) VALUES (?,?,?,?)");
        $uid = $_SESSION['admin_id'] ?? 1; $q->bind_param("ssii", $t, $s, $o, $uid);
        $out['success'] = $q->execute(); $out['message'] = $out['success'] ? 'Album created successfully' : ('Error: ' . $conn->error); $q->close();
    } elseif ($a === 'update_album') {
        $id = (int)($_POST['album_id'] ?? 0); $t = trim($_POST['title'] ?? ''); $s = trim($_POST['subtitle'] ?? ''); $o = (int)($_POST['display_order'] ?? 0);
        $q = $conn->prepare("UPDATE gallery_albums SET title=?, subtitle=?, display_order=? WHERE id=?"); $q->bind_param("ssii", $t, $s, $o, $id);
        $out['success'] = $q->execute(); $out['message'] = $out['success'] ? 'Album updated successfully' : ('Error: ' . $conn->error); $q->close();
    } elseif ($a === 'delete_album') {
        $id = (int)($_POST['album_id'] ?? 0);
        $q = $conn->prepare("SELECT file_path FROM gallery_images WHERE album_id = ?"); $q->bind_param("i", $id); $q->execute(); $r = $q->get_result();
        while ($row = $r->fetch_assoc()) { $p = $upload_dir . ($row['file_path'] ?? ''); if (!empty($row['file_path']) && file_exists($p)) @unlink($p); } $q->close();
        $d = $conn->prepare("DELETE FROM gallery_albums WHERE id = ?"); $d->bind_param("i", $id);
        $out['success'] = $d->execute(); $out['message'] = $out['success'] ? 'Album deleted successfully' : ('Error: ' . $conn->error); $d->close();
    } elseif ($a === 'upload_image') {
        $album_id = (int)($_POST['album_id'] ?? 0); $title = trim($_POST['title'] ?? ''); $description = trim($_POST['description'] ?? '');
        if ($album_id <= 0 || !isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) { $out['message'] = 'Invalid upload request'; echo json_encode($out); exit; }
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION)); $allow = ['jpg','jpeg','png','gif','webp'];
        if (!in_array($ext, $allow)) { $out['message'] = 'Invalid file type'; echo json_encode($out); exit; }
        $new = uniqid() . '.' . $ext; $target = rtrim($upload_dir, '/') . '/' . $new;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $q = $conn->prepare("INSERT INTO gallery_images (album_id,file_name,file_path,title,description) VALUES (?,?,?,?,?)");
            $orig = $_FILES['image']['name']; $q->bind_param("issss", $album_id, $orig, $new, $title, $description);
            $ok = $q->execute(); $q->close();
            $out['success'] = $ok; $out['message'] = $ok ? 'Image uploaded successfully' : 'Database error';
            if (!$ok) @unlink($target);
        } else $out['message'] = 'Failed to move uploaded file';
    } elseif ($a === 'move_image') {
        $id = (int)($_POST['image_id'] ?? 0); $to = (int)($_POST['new_album_id'] ?? 0);
        $q = $conn->prepare("UPDATE gallery_images SET album_id=? WHERE id=?"); $q->bind_param("ii", $to, $id);
        $out['success'] = $q->execute(); $out['message'] = $out['success'] ? 'Image moved successfully' : ('Error: ' . $conn->error); $q->close();
    } elseif ($a === 'archive_image') {
        $id = (int)($_POST['image_id'] ?? 0); $q = $conn->prepare("UPDATE gallery_images SET status='archived' WHERE id=?"); $q->bind_param("i", $id);
        $out['success'] = $q->execute(); $out['message'] = $out['success'] ? 'Image archived successfully' : ('Error: ' . $conn->error); $q->close();
    } elseif ($a === 'toggle_highlight') {
        $id = (int)($_POST['image_id'] ?? 0);
        $g = $conn->prepare("SELECT is_highlight FROM gallery_images WHERE id=?"); $g->bind_param("i", $id); $g->execute(); $row = $g->get_result()->fetch_assoc(); $g->close();
        $new = !((int)($row['is_highlight'] ?? 0)) ? 1 : 0;
        $q = $conn->prepare("UPDATE gallery_images SET is_highlight=? WHERE id=?"); $q->bind_param("ii", $new, $id);
        $out['success'] = $q->execute(); $out['message'] = $out['success'] ? ($new ? 'Image highlighted successfully' : 'Highlight removed successfully') : ('Error: ' . $conn->error); $out['is_highlight'] = $new; $q->close();
    }
    echo json_encode($out); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax'])) { header("Location: ad_gallery.php"); exit(); }

$albums = []; $ra = $conn->query("SELECT a.*, (SELECT COUNT(*) FROM gallery_images WHERE album_id=a.id) image_count FROM gallery_albums a WHERE a.status='active' ORDER BY a.display_order ASC, a.created_at DESC");
if ($ra) while ($r = $ra->fetch_assoc()) $albums[] = $r;
$selected_album_id = isset($_GET['album_id']) ? (int)$_GET['album_id'] : (count($albums) ? (int)$albums[0]['id'] : 0);
$selected_album = null; $album_images = [];
if ($selected_album_id > 0) {
    $qa = $conn->prepare("SELECT * FROM gallery_albums WHERE id=? AND status='active'"); $qa->bind_param("i", $selected_album_id); $qa->execute(); $selected_album = $qa->get_result()->fetch_assoc(); $qa->close();
    if ($selected_album) { $qi = $conn->prepare("SELECT * FROM gallery_images WHERE album_id=? AND (status IS NULL OR status='active') ORDER BY is_highlight DESC, display_order ASC, created_at DESC"); $qi->bind_param("i", $selected_album_id); $qi->execute(); $ri = $qi->get_result(); while ($x = $ri->fetch_assoc()) $album_images[] = $x; $qi->close(); }
}
$all_albums = $albums;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gallery Management — Admin</title>
  <link rel="icon" href="olfulogo.png" type="image/png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    :root{--g50:#edfaf3;--g100:#c6f0d9;--g200:#88e0b4;--g500:#16a05a;--g600:#0e7940;--g700:#085c2f;--a400:#f0b429;--b50:#edf4ff;--b600:#1d4ed8;--r50:#fef2f2;--r600:#dc2626;--s50:#f9fafb;--s100:#f3f4f6;--s200:#e5e7eb;--s300:#d1d5db;--s400:#9ca3af;--s500:#6b7280;--s700:#374151;--s800:#1f2937;--s900:#111827;--sm:0 1px 3px rgba(0,0,0,.06),0 1px 2px rgba(0,0,0,.04);--md:0 4px 12px rgba(0,0,0,.08),0 2px 4px rgba(0,0,0,.04);--lg:0 10px 30px rgba(0,0,0,.1),0 4px 8px rgba(0,0,0,.06)}
    *{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,sans-serif;background:#f5f6f8;color:var(--s800)} .wrap{padding-top:80px;padding-left:72px;min-height:100vh}.inner{max-width:1400px;margin:0 auto;padding:28px 24px 48px}
    .title{font-size:1.6rem;font-weight:700;color:var(--s900)} .sub{font-size:.82rem;color:var(--s500)} .grid{display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:start}
    .card{background:#fff;border:1px solid var(--s200);border-radius:10px;box-shadow:var(--sm)} .head{display:flex;justify-content:space-between;align-items:center;padding:18px 18px 12px;border-bottom:1px solid var(--s100)}
    .btn-new{font-size:12px;color:var(--g600);background:var(--g50);border:1px solid var(--g200);border-radius:999px;padding:6px 10px;cursor:pointer}
    .albums{padding:10px}.album{border-radius:8px;margin-bottom:3px}.album:hover{background:var(--s50)}.album.active{background:var(--g50);border:1px solid var(--g200)}
    .album-in{display:flex;align-items:center;gap:10px;padding:10px 12px}.icon{width:34px;height:34px;border-radius:8px;background:var(--s100);display:flex;align-items:center;justify-content:center;color:var(--s400)}.album.active .icon{background:var(--g100);color:var(--g600)}
    .meta{flex:1;min-width:0}.name{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.count{font-size:11px;color:var(--s400)}.acts{display:flex;gap:2px;opacity:0}.album:hover .acts,.album.active .acts{opacity:1}
    .act{width:26px;height:26px;border:0;background:transparent;border-radius:6px;cursor:pointer}.act.edit{color:var(--b600)}.act.edit:hover{background:var(--b50)}.act.del{color:var(--r600)}.act.del:hover{background:var(--r50)}
    .panel-head{display:flex;justify-content:space-between;align-items:center;padding:20px 24px 16px;border-bottom:1px solid var(--s100)} .up{background:var(--g500);color:#fff;border:0;border-radius:8px;padding:8px 14px;cursor:pointer}
    .strip{display:flex;gap:18px;padding:0 24px 14px}.chip{font-size:12px;color:var(--s500)} .chip strong{color:var(--s700)} .dot{display:inline-block;width:6px;height:6px;border-radius:999px;background:var(--s300);margin-right:6px}.dot.a{background:var(--a400)}
    .images{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;padding:20px 24px 24px}.img{position:relative;overflow:hidden;aspect-ratio:1/1;border-radius:10px;background:var(--s100);border:2px solid transparent;box-shadow:var(--sm)}
    .img.hl{border-color:var(--a400)} .img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .3s ease} .img:hover img{transform:scale(1.06)}
    .badge{position:absolute;top:10px;left:10px;background:var(--a400);color:#fff;font-size:10px;font-weight:700;border-radius:999px;padding:4px 8px;z-index:3}
    .ov{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.72),rgba(0,0,0,.2),rgba(0,0,0,0));display:flex;align-items:flex-end;justify-content:center;opacity:0;transition:opacity .2s}.img:hover .ov{opacity:1}
    .ovw{display:flex;gap:6px;margin-bottom:12px}.ob{width:34px;height:34px;border:0;border-radius:8px;color:#fff;cursor:pointer}.ob.s{background:rgba(240,180,41,.92)}.ob.m{background:rgba(59,130,246,.9)}.ob.a{background:rgba(100,116,139,.9)}
    .empty{text-align:center;padding:56px 20px;color:var(--s400)} .modal-bg{position:fixed;inset:0;background:rgba(15,20,35,.5);backdrop-filter:blur(3px);display:none;align-items:center;justify-content:center;padding:16px;z-index:9000}.modal-bg.open{display:flex}
    .modal{background:#fff;border-radius:14px;max-width:460px;width:100%;box-shadow:var(--lg)} .mh{display:flex;justify-content:space-between;align-items:center;padding:18px 22px;border-bottom:1px solid var(--s100)} .mb{padding:18px 22px} .mf{padding:14px 22px;border-top:1px solid var(--s100);display:flex;justify-content:flex-end;gap:10px}
    .in,.ta,.sel{width:100%;padding:9px 12px;border:1.5px solid var(--s200);border-radius:8px;background:var(--s50)} .in:focus,.ta:focus,.sel:focus{outline:none;border-color:var(--g500);box-shadow:0 0 0 3px rgba(22,160,90,.12);background:#fff}
    .drop{border:2px dashed var(--s200);border-radius:10px;background:var(--s50);padding:22px;text-align:center;cursor:pointer;position:relative}.drop.drag{border-color:var(--g500);background:var(--g50)} .drop input{position:absolute;inset:0;opacity:0;cursor:pointer}
    .light{position:fixed;inset:0;background:rgba(0,0,0,.88);display:none;align-items:center;justify-content:center;z-index:10000}.light.open{display:flex}.light img{max-width:90vw;max-height:88vh;border-radius:10px}
    .toast-wrap{position:fixed;bottom:24px;right:24px;z-index:20000;display:flex;flex-direction:column;gap:10px}.toast{background:var(--s900);color:#fff;padding:11px 14px;border-radius:10px;box-shadow:var(--md);font-size:13px}
  </style>
</head>
<body>
<?php include 'ad_header_universal.php'; ?><?php include 'ad_sidebar_universal.php'; ?>
<div class="wrap"><div class="inner">
  <div style="margin-bottom:22px"><div class="title">Gallery Management</div><div class="sub">Manage photo albums and images for the alumni portal</div></div>
  <div class="grid">
    <div class="card" style="position:sticky;top:90px">
      <div class="head"><div style="font-size:11px;color:var(--s400);font-weight:700;letter-spacing:.08em;text-transform:uppercase">Albums</div><button id="createAlbumBtn" class="btn-new"><i class="fas fa-plus"></i> New Album</button></div>
      <div class="albums">
        <?php if (empty($albums)): ?><div class="empty"><i class="fas fa-folder-open"></i><div>No albums yet.</div></div>
        <?php else: foreach ($albums as $album): ?>
          <div class="album <?php echo $selected_album_id == $album['id'] ? 'active' : ''; ?>">
            <div class="album-in">
              <div class="icon"><i class="fas fa-images"></i></div>
              <div class="meta" onclick="selectAlbum(<?php echo $album['id']; ?>)">
                <div class="name"><?php echo htmlspecialchars($album['title']); ?></div>
                <div class="count"><?php echo (int)$album['image_count']; ?> image<?php echo $album['image_count'] != 1 ? 's' : ''; ?><?php if (!empty($album['subtitle'])): ?> · <?php echo htmlspecialchars(mb_strimwidth($album['subtitle'], 0, 22, '…')); ?><?php endif; ?></div>
              </div>
              <div class="acts">
                <button class="act edit" onclick="editAlbum(<?php echo $album['id']; ?>,'<?php echo htmlspecialchars(addslashes($album['title'])); ?>','<?php echo htmlspecialchars(addslashes($album['subtitle'] ?? '')); ?>',<?php echo (int)$album['display_order']; ?>)"><i class="fas fa-pen"></i></button>
                <button class="act del" onclick="deleteAlbum(<?php echo $album['id']; ?>)"><i class="fas fa-trash"></i></button>
              </div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <div class="card">
      <?php if ($selected_album): ?>
      <div class="panel-head">
        <div><div style="font-size:16px;font-weight:700;color:var(--s900)"><?php echo htmlspecialchars($selected_album['title']); ?></div><div style="font-size:12px;color:var(--s400)"><?php echo !empty($selected_album['subtitle']) ? htmlspecialchars($selected_album['subtitle']) : (count($album_images) . ' image' . (count($album_images) != 1 ? 's' : '') . ' in this album'); ?></div></div>
        <button id="uploadImageBtn" class="up"><i class="fas fa-cloud-upload-alt"></i> Upload Images</button>
      </div>
      <?php $total = count($album_images); $hl = count(array_filter($album_images, fn($i) => $i['is_highlight'] ?? 0)); if ($total > 0): ?>
      <div class="strip"><div class="chip"><span class="dot"></span><strong><?php echo $total; ?></strong> total</div><?php if ($hl > 0): ?><div class="chip"><span class="dot a"></span><strong><?php echo $hl; ?></strong> highlighted</div><?php endif; ?></div>
      <?php endif; ?>
      <div class="images">
        <?php if (empty($album_images)): ?><div class="empty" style="grid-column:1/-1"><i class="fas fa-image"></i><div>No photos yet</div></div>
        <?php else: foreach ($album_images as $image): $p = 'serve_gallery_image.php?img=' . urlencode(trim($image['file_path'])); $is = (int)($image['is_highlight'] ?? 0); ?>
          <div class="img <?php echo $is ? 'hl' : ''; ?>">
            <?php if ($is): ?><div class="badge"><i class="fas fa-star"></i> Highlight</div><?php endif; ?>
            <img src="<?php echo $p; ?>" alt="<?php echo htmlspecialchars($image['title'] ?? $image['file_name']); ?>" onclick="openLightbox('<?php echo $p; ?>')" onerror="this.onerror=null;this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22%3E%3Crect fill=%22%23e5e7eb%22 width=%22200%22 height=%22200%22/%3E%3Ctext fill=%22%239ca3af%22 font-family=%22sans-serif%22 font-size=%2212%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.35em%22%3ENo image%3C/text%3E%3C/svg%3E';">
            <div class="ov"><div class="ovw">
              <button class="ob s" onclick="toggleHighlight(<?php echo (int)$image['id']; ?>,<?php echo $is; ?>)"><i class="fas fa-star"></i></button>
              <button class="ob m" onclick="moveImage(<?php echo (int)$image['id']; ?>,<?php echo (int)$selected_album_id; ?>)"><i class="fas fa-folder-open"></i></button>
              <button class="ob a" onclick="archiveImage(<?php echo (int)$image['id']; ?>)"><i class="fas fa-archive"></i></button>
            </div></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
      <?php else: ?><div class="empty" style="padding:90px 20px"><i class="fas fa-folder-open"></i><div>No album selected</div></div><?php endif; ?>
    </div>
  </div>
</div></div>

<div id="createAlbumModal" class="modal-bg"><div class="modal"><div class="mh"><b>Create New Album</b><button onclick="closeModal('createAlbumModal')">✕</button></div><form id="createAlbumForm"><input type="hidden" name="ajax" value="1"><input type="hidden" name="action" value="create_album"><input type="hidden" name="display_order" value="0"><div class="mb"><label>Album Title *</label><input class="in" name="title" required><div style="height:10px"></div><label>Subtitle</label><input class="in" name="subtitle"></div><div class="mf"><button type="button" onclick="closeModal('createAlbumModal')">Cancel</button><button class="up" type="submit">Create</button></div></form></div></div>
<div id="editAlbumModal" class="modal-bg"><div class="modal"><div class="mh"><b>Edit Album</b><button onclick="closeModal('editAlbumModal')">✕</button></div><form id="editAlbumForm"><input type="hidden" name="ajax" value="1"><input type="hidden" name="action" value="update_album"><input type="hidden" name="album_id" id="editAlbumId"><div class="mb"><label>Album Title *</label><input class="in" name="title" id="editAlbumTitle" required><div style="height:10px"></div><label>Subtitle</label><input class="in" name="subtitle" id="editAlbumSubtitle"><div style="height:10px"></div><label>Display Order</label><input class="in" type="number" name="display_order" id="editAlbumOrder" min="0"></div><div class="mf"><button type="button" onclick="closeModal('editAlbumModal')">Cancel</button><button class="up" type="submit">Save</button></div></form></div></div>
<div id="uploadModal" class="modal-bg"><div class="modal"><div class="mh"><b>Upload Image</b><button onclick="closeModal('uploadModal')">✕</button></div><form id="uploadImageForm" enctype="multipart/form-data"><input type="hidden" name="ajax" value="1"><input type="hidden" name="action" value="upload_image"><input type="hidden" name="album_id" id="uploadAlbumId" value="<?php echo (int)$selected_album_id; ?>"><div class="mb"><label>Photo *</label><div class="drop" id="fileDrop"><i class="fas fa-cloud-upload-alt"></i><div>Drop image here or click to browse</div><input type="file" name="image" id="imageFileInput" accept="image/*" required></div><div id="fileMeta" style="font-size:12px;color:var(--s500);margin-top:8px"></div><div style="height:10px"></div><label>Title</label><input class="in" name="title"><div style="height:10px"></div><label>Description</label><textarea class="ta" name="description"></textarea></div><div class="mf"><button type="button" onclick="closeModal('uploadModal')">Cancel</button><button class="up" type="submit" id="uploadSubmitBtn"><i class="fas fa-cloud-upload-alt"></i> Upload</button></div></form></div></div>
<div id="moveModal" class="modal-bg"><div class="modal"><div class="mh"><b>Move to Album</b><button onclick="closeModal('moveModal')">✕</button></div><form id="moveImageForm"><input type="hidden" name="ajax" value="1"><input type="hidden" name="action" value="move_image"><input type="hidden" name="image_id" id="moveImageId"><div class="mb"><label>Select destination album</label><select class="sel" name="new_album_id" id="moveAlbumSelect" required><option value="">Choose album…</option><?php foreach ($all_albums as $album): ?><option value="<?php echo (int)$album['id']; ?>"><?php echo htmlspecialchars($album['title']); ?></option><?php endforeach; ?></select></div><div class="mf"><button type="button" onclick="closeModal('moveModal')">Cancel</button><button class="up" type="submit">Move</button></div></form></div></div>
<div id="lightbox" class="light" onclick="closeLightbox(event)"><button style="position:absolute;top:14px;right:20px" onclick="closeLightboxBtn()">✕</button><img id="lightboxImg" src="" alt=""></div>
<div class="toast-wrap" id="toastWrap"></div>

<script>
const showToast=(m,t='success')=>{const w=document.getElementById('toastWrap');const d=document.createElement('div');d.className='toast';d.innerHTML=`<i class="fas fa-${t==='success'?'check-circle':'exclamation-circle'}"></i> ${m}`;w.appendChild(d);setTimeout(()=>{d.style.opacity='0';d.style.transform='translateX(20px)';d.style.transition='.3s';setTimeout(()=>d.remove(),300)},3000)};
const openModal=id=>{document.getElementById(id).classList.add('open');document.body.style.overflow='hidden'}; const closeModal=id=>{document.getElementById(id).classList.remove('open');document.body.style.overflow=''};
const openLightbox=src=>{document.getElementById('lightboxImg').src=src;document.getElementById('lightbox').classList.add('open');document.body.style.overflow='hidden'};
const closeLightbox=e=>{if(e.target===document.getElementById('lightbox')) closeLightboxBtn()}; const closeLightboxBtn=()=>{document.getElementById('lightbox').classList.remove('open');document.body.style.overflow=''};
document.addEventListener('keydown',e=>{if(e.key==='Escape'){document.querySelectorAll('.modal-bg.open').forEach(m=>closeModal(m.id));closeLightboxBtn();}});
document.querySelectorAll('.modal-bg').forEach(m=>m.addEventListener('click',e=>{if(e.target===m) closeModal(m.id)}));

const postAjax=(data,onSuccess)=>{const fd=new FormData();Object.entries(data).forEach(([k,v])=>fd.append(k,v));fetch('ad_gallery.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>d.success?onSuccess(d):showToast(d.message||'Something went wrong','error')).catch(()=>showToast('Network error','error'))};
document.getElementById('createAlbumBtn')?.addEventListener('click',()=>{document.getElementById('createAlbumForm').reset();openModal('createAlbumModal')});
function editAlbum(id,title,subtitle,order){document.getElementById('editAlbumId').value=id;document.getElementById('editAlbumTitle').value=title;document.getElementById('editAlbumSubtitle').value=subtitle;document.getElementById('editAlbumOrder').value=order;openModal('editAlbumModal')}
function deleteAlbum(id){if(!confirm('Delete this album and all its images? This cannot be undone.'))return;postAjax({ajax:1,action:'delete_album',album_id:id},()=>location.reload())}
function selectAlbum(id){window.location.href='ad_gallery.php?album_id='+id}
document.getElementById('uploadImageBtn')?.addEventListener('click',()=>{const a=<?php echo (int)$selected_album_id; ?>;if(!a){showToast('Please select an album first','error');return;}document.getElementById('uploadImageForm').reset();document.getElementById('uploadAlbumId').value=a;document.getElementById('fileMeta').textContent='';openModal('uploadModal')});
function moveImage(id,currentAlbumId){document.getElementById('moveImageId').value=id;const s=document.getElementById('moveAlbumSelect');Array.from(s.options).forEach(o=>o.hidden=o.value==currentAlbumId);s.value='';openModal('moveModal')}
function archiveImage(id){if(!confirm('Archive this image?')) return;postAjax({ajax:1,action:'archive_image',image_id:id},()=>{showToast('Image archived');setTimeout(()=>location.reload(),600)})}
function toggleHighlight(id){postAjax({ajax:1,action:'toggle_highlight',image_id:id},d=>{showToast(d.is_highlight?'Image highlighted':'Highlight removed');setTimeout(()=>location.reload(),600)})}

document.getElementById('imageFileInput')?.addEventListener('change',e=>{const f=e.target.files?.[0];document.getElementById('fileMeta').textContent=f?`${f.name} • ${(f.size/1024).toFixed(1)} KB`:''});
const drop=document.getElementById('fileDrop'); drop?.addEventListener('dragover',e=>{e.preventDefault();drop.classList.add('drag')}); drop?.addEventListener('dragleave',()=>drop.classList.remove('drag'));
drop?.addEventListener('drop',e=>{e.preventDefault();drop.classList.remove('drag');const f=e.dataTransfer.files?.[0];if(f){const i=document.getElementById('imageFileInput');i.files=e.dataTransfer.files;document.getElementById('fileMeta').textContent=`${f.name} • ${(f.size/1024).toFixed(1)} KB`}});

document.getElementById('createAlbumForm')?.addEventListener('submit',function(e){e.preventDefault();postAjax(Object.fromEntries(new FormData(this)),()=>location.reload())});
document.getElementById('editAlbumForm')?.addEventListener('submit',function(e){e.preventDefault();postAjax(Object.fromEntries(new FormData(this)),()=>{closeModal('editAlbumModal');showToast('Album updated');setTimeout(()=>location.reload(),600)})});
document.getElementById('moveImageForm')?.addEventListener('submit',function(e){e.preventDefault();postAjax(Object.fromEntries(new FormData(this)),()=>{closeModal('moveModal');showToast('Image moved');setTimeout(()=>location.reload(),600)})});
document.getElementById('uploadImageForm')?.addEventListener('submit',function(e){e.preventDefault();const fd=new FormData(this);const b=document.getElementById('uploadSubmitBtn');b.disabled=true;b.innerHTML='<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:999px;animation:spin .6s linear infinite"></span> Uploading...';fetch('ad_gallery.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{b.disabled=false;b.innerHTML='<i class="fas fa-cloud-upload-alt"></i> Upload';if(d.success){closeModal('uploadModal');showToast('Image uploaded successfully');const aid=new URLSearchParams(window.location.search).get('album_id')||fd.get('album_id');setTimeout(()=>window.location.href='ad_gallery.php?album_id='+aid,600)}else showToast(d.message||'Upload failed','error')}).catch(()=>{b.disabled=false;b.innerHTML='<i class="fas fa-cloud-upload-alt"></i> Upload';showToast('Upload error','error')})});
</script>
</body>
</html>
