<?php
session_start();
require_once 'db_config.php';
alumni_otp_gate_after_session();
$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header('Location: al_homepage.php');
    exit;
}

// Get user data
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log('Prepare failed for user query: ' . $conn->error);
    $user = [];
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

// Fetch notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log('Prepare failed for notifications query: ' . $conn->error);
    $notifications = [];
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
$notification_count = count(array_filter($notifications, function($n) { return !$n['is_read']; }));

// Unread messages count
$sql = "SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log('Prepare failed for unread messages count: ' . $conn->error);
    $unread_count = 0;
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $unread_count = $row ? (int)$row['unread_count'] : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Alumni Gallery - OLFU Alumni</title>
  <link rel="icon" href="olfulogo.png" type="image/png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Geist+Sans:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --brand-green: #047857; /* emerald-700 */
      --brand-light: #ecfdf5; /* emerald-50 */
    }

    body { 
      font-family: 'Geist Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
    }
    .h-elegant { 
      font-family: 'Geist Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
      letter-spacing: -0.01em; 
    }
    .section-title { 
      @apply text-2xl md:text-3xl font-extrabold h-elegant tracking-tight; 
    }
    .subheading { 
      @apply text-base md:text-lg text-gray-600; 
    }
    .gradient-bar { 
      background: linear-gradient(90deg, rgba(16,185,129,1) 0%, rgba(59,130,246,1) 100%); 
      height: 4px; 
      border-radius: 9999px; 
    }

    main {
      margin-left: 4rem;
      transition: margin-left 0.3s ease;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 1rem;
    }
    #sidebar:hover + main {
      margin-left: 16rem;
    }
  </style>
</head>
<body class="bg-gradient-to-b from-white to-emerald-50">
	<!-- Header (Universal Include) -->
	<?php include __DIR__ . '/al_header_universal.php'; ?>

  <!-- Main Content (Gallery) -->
  <main class="pt-8 pb-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto transition-all duration-300">
    <div class="mb-10">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="h-elegant text-3xl md:text-4xl font-extrabold text-emerald-800">Alumni Gallery</h1>
          <div class="gradient-bar mt-2 w-40"></div>
          <p class="subheading mt-3">Browse featured moments and memories from our community.</p>
        </div>
      </div>
    </div>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 bg-white rounded-lg shadow-sm border border-gray-100 p-4 mb-6">
      <div class="flex items-center gap-2">
        <a href="al_gallery.php" id="filterAll" class="px-3 py-1.5 text-sm rounded-md <?php echo !$show_highlights_only ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">All</a>
        <a href="al_gallery.php?highlights=1" id="filterHighlighted" class="px-3 py-1.5 text-sm rounded-md <?php echo $show_highlights_only ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
          <i class="fas fa-star mr-1"></i> Highlighted
        </a>
      </div>
      <div class="flex items-center gap-3">
        <div class="relative">
          <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
          <input id="searchInput" type="text" placeholder="Search photos..." class="pl-9 pr-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600 focus:border-green-600 w-64" />
        </div>
        <select id="sortSelect" class="py-2 px-3 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600 focus:border-green-600">
          <option value="newest">Newest first</option>
          <option value="oldest">Oldest first</option>
          <option value="name">Name (A-Z)</option>
        </select>
      </div>
    </div>

    <!-- Albums Grid -->
    <?php
    // Ensure gallery tables exist
    function ensureGalleryTables($conn) {
        $sql_albums = "CREATE TABLE IF NOT EXISTS gallery_albums (
            id INT PRIMARY KEY AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            subtitle TEXT,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status ENUM('active', 'archived') DEFAULT 'active',
            created_by INT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $sql_images = "CREATE TABLE IF NOT EXISTS gallery_images (
            id INT PRIMARY KEY AUTO_INCREMENT,
            album_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            title VARCHAR(255),
            description TEXT,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $conn->query($sql_albums);
        $conn->query($sql_images);
    }
    
    ensureGalleryTables($conn);
    
    // Check if is_highlight column exists, add it if it doesn't
    $highlight_check = $conn->query("SHOW COLUMNS FROM gallery_images LIKE 'is_highlight'");
    $has_highlight_column = $highlight_check && $highlight_check->num_rows > 0;
    if (!$has_highlight_column) {
        // Add is_highlight column
        $conn->query("ALTER TABLE gallery_images ADD COLUMN is_highlight TINYINT(1) DEFAULT 0 AFTER status");
        $has_highlight_column = true;
    }
    
    // Fetch active albums
    $upload_dir = 'uploads/gallery/';
    $selected_album_id = isset($_GET['album_id']) ? (int)$_GET['album_id'] : null;
    $show_highlights_only = isset($_GET['highlights']) && $_GET['highlights'] == '1';
    
    // If showing highlights only, fetch all highlighted images from all albums
    if ($show_highlights_only && $has_highlight_column) {
        $sql_highlights = "SELECT gi.*, ga.title as album_title 
                          FROM gallery_images gi 
                          INNER JOIN gallery_albums ga ON gi.album_id = ga.id 
                          WHERE (gi.status IS NULL OR gi.status = 'active') 
                          AND (ga.status = 'active' OR ga.status IS NULL)
                          AND gi.is_highlight = 1 
                          ORDER BY gi.created_at DESC";
        $result_highlights = $conn->query($sql_highlights);
        $highlighted_images = [];
        if ($result_highlights) {
            while ($row = $result_highlights->fetch_assoc()) {
                $highlighted_images[] = $row;
            }
        }
        ?>
        <div class="mb-6">
            <a href="al_gallery.php" class="text-green-600 hover:text-green-700 mb-4 inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Albums
            </a>
            <h2 class="text-2xl font-bold text-gray-800 mt-4 flex items-center gap-2">
                <i class="fas fa-star text-yellow-500"></i> Highlighted Images
            </h2>
            <p class="text-gray-600 mt-2">All featured images from all albums</p>
        </div>
        
        <div id="galleryGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <?php if (!empty($highlighted_images)): ?>
                <?php foreach ($highlighted_images as $image): ?>
                    <figure class="group relative overflow-hidden rounded-lg bg-gray-100 shadow-sm cursor-pointer ring-2 ring-yellow-400" onclick="openLightbox('serve_gallery_image.php?img=<?php echo urlencode($image['file_path']); ?>')">
                        <div class="absolute top-2 right-2 z-10 bg-yellow-500 text-white px-2 py-1 rounded-full text-xs font-semibold flex items-center gap-1 shadow-lg">
                            <i class="fas fa-star"></i> Highlight
                        </div>
                        <img src="serve_gallery_image.php?img=<?php echo urlencode($image['file_path']); ?>" 
                             alt="<?php echo htmlspecialchars($image['title'] ?? $image['file_name'] ?? 'Gallery Image'); ?>" 
                             loading="lazy" 
                             class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105" />
                        <figcaption class="pointer-events-none absolute inset-0 bg-black/0 group-hover:bg-black/30 transition flex items-end p-3">
                            <div class="text-white opacity-0 group-hover:opacity-100 transition w-full">
                                <span class="text-xs truncate block"><?php echo htmlspecialchars($image['title'] ?? $image['file_name'] ?? 'Image'); ?></span>
                                <?php if (!empty($image['album_title'])): ?>
                                    <span class="text-xs truncate block mt-1 opacity-90">From: <?php echo htmlspecialchars($image['album_title']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($image['description'])): ?>
                                    <span class="text-xs truncate block mt-1 opacity-90"><?php echo htmlspecialchars($image['description']); ?></span>
                                <?php endif; ?>
                            </div>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-12 text-gray-500">
                    <i class="fas fa-star text-4xl mb-3 text-gray-400"></i>
                    <p>No highlighted images found.</p>
                    <p class="text-sm mt-2">Highlighted images will appear here when admins mark them as highlights.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    } else if ($selected_album_id) {
        // Show images in selected album
        $sql_album = "SELECT * FROM gallery_albums WHERE id = ? AND status = 'active'";
        $stmt_album = $conn->prepare($sql_album);
        $stmt_album->bind_param("i", $selected_album_id);
        $stmt_album->execute();
        $result_album = $stmt_album->get_result();
        $current_album = $result_album->fetch_assoc();
        $stmt_album->close();
        
        if ($current_album) {
            // Fetch images for this album (only active images, highlights first)
            if ($has_highlight_column) {
                $sql_images = "SELECT * FROM gallery_images WHERE album_id = ? AND (status IS NULL OR status = 'active') ORDER BY is_highlight DESC, display_order ASC, created_at DESC";
            } else {
                $sql_images = "SELECT * FROM gallery_images WHERE album_id = ? AND (status IS NULL OR status = 'active') ORDER BY display_order ASC, created_at DESC";
            }
            $stmt_images = $conn->prepare($sql_images);
            $stmt_images->bind_param("i", $selected_album_id);
            $stmt_images->execute();
            $result_images = $stmt_images->get_result();
            $album_images = [];
            while ($row = $result_images->fetch_assoc()) {
                $album_images[] = $row;
            }
            $stmt_images->close();
            ?>
            <div class="mb-6">
                <a href="al_gallery.php" class="text-green-600 hover:text-green-700 mb-4 inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Albums
                </a>
                <h2 class="text-2xl font-bold text-gray-800 mt-4"><?php echo htmlspecialchars($current_album['title']); ?></h2>
                <?php if (!empty($current_album['subtitle'])): ?>
                    <p class="text-gray-600 mt-2"><?php echo htmlspecialchars($current_album['subtitle']); ?></p>
                <?php endif; ?>
            </div>
            
            <div id="galleryGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($album_images as $image): ?>
                    <?php 
                    // Check if image is highlighted (handle both int and string values)
                    $is_highlighted = isset($image['is_highlight']) && ($image['is_highlight'] == 1 || $image['is_highlight'] === '1');
                    ?>
                    <figure class="group relative overflow-hidden rounded-lg bg-gray-100 shadow-sm cursor-pointer <?php echo $is_highlighted ? 'ring-2 ring-yellow-400' : ''; ?>" onclick="openLightbox('serve_gallery_image.php?img=<?php echo urlencode($image['file_path']); ?>')">
                        <?php if ($is_highlighted): ?>
                            <div class="absolute top-2 right-2 z-10 bg-yellow-500 text-white px-2 py-1 rounded-full text-xs font-semibold flex items-center gap-1 shadow-lg">
                                <i class="fas fa-star"></i> Highlight
                            </div>
                        <?php endif; ?>
                        <img src="serve_gallery_image.php?img=<?php echo urlencode($image['file_path']); ?>" 
                             alt="<?php echo htmlspecialchars($image['title'] ?? $image['file_name'] ?? 'Gallery Image'); ?>" 
                             loading="lazy" 
                             class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105" />
                        <figcaption class="pointer-events-none absolute inset-0 bg-black/0 group-hover:bg-black/30 transition flex items-end p-3">
                            <div class="text-white opacity-0 group-hover:opacity-100 transition w-full">
                                <span class="text-xs truncate block"><?php echo htmlspecialchars($image['title'] ?? $image['file_name'] ?? 'Image'); ?></span>
                                <?php if (!empty($image['description'])): ?>
                                    <span class="text-xs truncate block mt-1 opacity-90"><?php echo htmlspecialchars($image['description']); ?></span>
                                <?php endif; ?>
                            </div>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
            <?php
        } else {
            echo '<p class="text-center text-gray-500 py-20">Album not found.</p>';
        }
    } else {
        // Show albums
        $sql = "SELECT a.*, 
                (SELECT COUNT(*) FROM gallery_images WHERE album_id = a.id AND (status IS NULL OR status = 'active')) as image_count
                FROM gallery_albums a 
                WHERE a.status = 'active'
                ORDER BY a.display_order ASC, a.created_at DESC";
        $result = $conn->query($sql);
        $albums = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $albums[] = $row;
            }
        }
        ?>
        <div id="galleryGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($albums as $album): ?>
                <?php
                // Get first image as cover (only active images, prioritize highlighted)
                if ($has_highlight_column) {
                    $sql_cover = "SELECT file_path FROM gallery_images WHERE album_id = ? AND (status IS NULL OR status = 'active') ORDER BY is_highlight DESC, display_order ASC, created_at ASC LIMIT 1";
                } else {
                    $sql_cover = "SELECT file_path FROM gallery_images WHERE album_id = ? AND (status IS NULL OR status = 'active') ORDER BY display_order ASC, created_at ASC LIMIT 1";
                }
                $stmt_cover = $conn->prepare($sql_cover);
                $stmt_cover->bind_param("i", $album['id']);
                $stmt_cover->execute();
                $result_cover = $stmt_cover->get_result();
                $cover_image = $result_cover->fetch_assoc();
                $stmt_cover->close();
                ?>
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow cursor-pointer" onclick="window.location.href='?album_id=<?php echo $album['id']; ?>'">
                    <div class="relative h-64 bg-gray-200">
                        <?php if ($cover_image && !empty($cover_image['file_path'])): ?>
                            <img src="serve_gallery_image.php?img=<?php echo urlencode($cover_image['file_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($album['title']); ?>" 
                                 class="w-full h-full object-cover">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fas fa-images text-5xl text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                        <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-4">
                            <h3 class="text-white font-bold text-lg mb-1"><?php echo htmlspecialchars($album['title']); ?></h3>
                            <?php if (!empty($album['subtitle'])): ?>
                                <p class="text-white/90 text-sm"><?php echo htmlspecialchars($album['subtitle']); ?></p>
                            <?php endif; ?>
                            <p class="text-white/80 text-xs mt-2">
                                <i class="fas fa-images mr-1"></i><?php echo $album['image_count']; ?> images
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    ?>

    <div id="emptyState" class="hidden text-center text-gray-500 py-20">
      <i class="fas fa-image text-4xl mb-3"></i>
      <p>No images match your filters.</p>
    </div>
  </main>

  <div id="lightbox" class="fixed inset-0 bg-black/80 hidden items-center justify-center z-50">
    <button id="lightboxClose" class="absolute top-4 right-4 text-white text-2xl" aria-label="Close">&times;</button>
    <button id="lightboxPrev" class="absolute left-4 top-1/2 -translate-y-1/2 text-white text-2xl p-2 bg-white/10 rounded-full hover:bg-white/20" aria-label="Previous">
      <i class="fas fa-chevron-left"></i>
    </button>
    <img id="lightboxImg" src="" alt="Preview" class="max-h-[85vh] max-w-[90vw] object-contain rounded shadow-lg" />
    <button id="lightboxNext" class="absolute right-4 top-1/2 -translate-y-1/2 text-white text-2xl p-2 bg-white/10 rounded-full hover:bg-white/20" aria-label="Next">
      <i class="fas fa-chevron-right"></i>
    </button>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Gallery filtering and search (only for album view, not highlights/albums page)
      const filterAll = document.getElementById('filterAll');
      const filterHighlighted = document.getElementById('filterHighlighted');
      const searchInput = document.getElementById('searchInput');
      const sortSelect = document.getElementById('sortSelect');
      const galleryGrid = document.getElementById('galleryGrid');
      const emptyState = document.getElementById('emptyState');
      
      // Check if we're viewing a specific album (not highlights page or albums list)
      const urlParams = new URLSearchParams(window.location.search);
      const isHighlightsPage = urlParams.get('highlights') === '1';
      const isAlbumView = urlParams.get('album_id') !== null;
      
      // Only apply client-side filtering when viewing a specific album
      if (isAlbumView && galleryGrid) {
        let currentFilter = 'all'; // 'all' or 'highlighted'
        
        // Filter button handlers (prevent default navigation when in album view)
        if (filterAll && filterHighlighted) {
          filterAll.addEventListener('click', function(e) {
            e.preventDefault();
            currentFilter = 'all';
            filterAll.classList.remove('bg-gray-100', 'text-gray-700');
            filterAll.classList.add('bg-green-600', 'text-white');
            filterHighlighted.classList.remove('bg-green-600', 'text-white');
            filterHighlighted.classList.add('bg-gray-100', 'text-gray-700');
            applyFilters();
          });
          
          filterHighlighted.addEventListener('click', function(e) {
            e.preventDefault();
            currentFilter = 'highlighted';
            filterHighlighted.classList.remove('bg-gray-100', 'text-gray-700');
            filterHighlighted.classList.add('bg-green-600', 'text-white');
            filterAll.classList.remove('bg-green-600', 'text-white');
            filterAll.classList.add('bg-gray-100', 'text-gray-700');
            applyFilters();
          });
        }
        
        function applyFilters() {
          if (!galleryGrid) return;
          
          const images = galleryGrid.querySelectorAll('figure');
          let visibleCount = 0;
          
          images.forEach(function(img) {
            // Check for highlight indicator: ring-2 class (yellow border) or .bg-yellow-500 badge
            const hasRing = img.classList.contains('ring-2');
            const hasBadge = img.querySelector('.bg-yellow-500');
            const isHighlighted = hasRing || hasBadge !== null;
            let shouldShow = true;
            
            // Apply highlight filter
            if (currentFilter === 'highlighted' && !isHighlighted) {
              shouldShow = false;
            }
            
            // Apply search filter
            if (shouldShow && searchInput && searchInput.value.trim()) {
              const searchTerm = searchInput.value.toLowerCase();
              const title = img.querySelector('img')?.alt?.toLowerCase() || '';
              const description = img.querySelector('.text-xs')?.textContent?.toLowerCase() || '';
              if (!title.includes(searchTerm) && !description.includes(searchTerm)) {
                shouldShow = false;
              }
            }
            
            if (shouldShow) {
              img.style.display = '';
              visibleCount++;
            } else {
              img.style.display = 'none';
            }
          });
          
          // Show/hide empty state
          if (emptyState) {
            if (visibleCount === 0) {
              emptyState.classList.remove('hidden');
              galleryGrid.style.display = 'none';
            } else {
              emptyState.classList.add('hidden');
              galleryGrid.style.display = '';
            }
          }
        }
        
        // Search input handler
        if (searchInput) {
          searchInput.addEventListener('input', applyFilters);
        }
        
        // Sort handler (if needed)
        if (sortSelect) {
          sortSelect.addEventListener('change', function() {
            // Sort functionality can be added here if needed
            applyFilters();
          });
        }
      }
      
      // Lightbox functionality
      const lightbox = document.getElementById('lightbox');
      const lightboxImg = document.getElementById('lightboxImg');
      const lightboxClose = document.getElementById('lightboxClose');
      const lightboxPrev = document.getElementById('lightboxPrev');
      const lightboxNext = document.getElementById('lightboxNext');
      
      let currentImages = [];
      let currentIndex = 0;
      
      function openLightbox(src) {
        // Get all images in current view
        const images = Array.from(document.querySelectorAll('#galleryGrid img'));
        currentImages = images.map(img => img.src);
        currentIndex = currentImages.indexOf(src);
        
        if (currentIndex === -1) currentIndex = 0;
        
        lightboxImg.src = currentImages[currentIndex];
        lightbox.classList.remove('hidden');
        lightbox.classList.add('flex');
        document.body.style.overflow = 'hidden';
      }
      
      function closeLightbox() {
        lightbox.classList.add('hidden');
        lightbox.classList.remove('flex');
        document.body.style.overflow = '';
      }
      
      function showNext() {
        if (currentImages.length > 0) {
          currentIndex = (currentIndex + 1) % currentImages.length;
          lightboxImg.src = currentImages[currentIndex];
        }
      }
      
      function showPrev() {
        if (currentImages.length > 0) {
          currentIndex = (currentIndex - 1 + currentImages.length) % currentImages.length;
          lightboxImg.src = currentImages[currentIndex];
        }
      }
      
      if (lightboxClose) lightboxClose.addEventListener('click', closeLightbox);
      if (lightboxPrev) lightboxPrev.addEventListener('click', showPrev);
      if (lightboxNext) lightboxNext.addEventListener('click', showNext);
      
      lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox) closeLightbox();
      });
      
      document.addEventListener('keydown', function(e) {
        if (!lightbox.classList.contains('hidden')) {
          if (e.key === 'Escape') closeLightbox();
          if (e.key === 'ArrowRight') showNext();
          if (e.key === 'ArrowLeft') showPrev();
        }
      });
      
      // Make openLightbox available globally
      window.openLightbox = openLightbox;
    });
  </script>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) { include 'al_footer_universal.php'; } ?>
</body>
</html>

