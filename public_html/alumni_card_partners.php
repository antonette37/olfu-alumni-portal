<?php
session_start();
require_once 'db_config.php';
$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
	header('Location: al_homepage.php');
	exit;
}

$user_id = (int)$_SESSION['user_id'];

// Fetch user data (for header/sidebar)
$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) { $stmt->bind_param("i", $user_id); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); $stmt->close(); } else { $user = []; }

// Fetch notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($stmt) { $stmt->bind_param("i", $user_id); $stmt->execute(); $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); } else { $notifications = []; }
$notification_count = count(array_filter($notifications, function($n){ return empty($n['is_read']); }));
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Alumni Card Partners - OLFU Alumni</title>
	<script src="https://cdn.tailwindcss.com"></script>
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
	<link href="https://fonts.googleapis.com/css2?family=Geist+Sans:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
	<style>
		.h-elegant { 
			font-family: 'Geist Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
			letter-spacing: -0.01em; 
		}
		.subheading { 
			@apply text-base md:text-lg text-gray-600; 
		}
		.gradient-bar { 
			background: linear-gradient(90deg, rgba(16,185,129,1) 0%, rgba(59,130,246,1) 100%); 
			height: 4px; 
			border-radius: 9999px; 
		}
	</style>
</head>
<body class="bg-gradient-to-b from-white to-emerald-50">
	<?php include __DIR__ . '/al_header_universal.php'; ?>

	<!-- Main Content -->
	<main class="pt-8 pb-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
		<!-- Page Header -->
		<div class="mb-10">
			<div class="flex items-center justify-between">
				<div>
					<h1 class="h-elegant text-3xl md:text-4xl font-extrabold text-emerald-800">Alumni Card Partners</h1>
					<div class="gradient-bar mt-2 w-40"></div>
					<p class="subheading mt-3">Browse the full list of partner establishments where you can enjoy exclusive discounts and privileges.</p>
				</div>
			</div>
		</div>

		<!-- Partners Grid -->
		<section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
			<?php 
			$partners = [
				[
					'name' => 'Azalea Hotels and Residences (Baguio and Boracay)',
					'image' => 'azalea.jpg',
					'desc' => 'Discount offer extended to all Our Lady of Fatima University Alumni Members, Employees, and Students and Fatima University Medical Center Employees.',
					'link_text' => 'Special room rates',
					'link_url' => 'https://bit.ly/Azalea-Room-Rates',
					'valid' => 'December 20, 2024',
				],
				[
					'name' => 'Richmonde Hotel (Ortigas)',
					'image' => 'rich.jpg',
					'desc' => 'Discount offer extended to all Our Lady of Fatima University Alumni Members, Employees, and Students and Fatima University Medical Center Employees.',
					'link_text' => 'Special room rates',
					'link_url' => 'https://bit.ly/Richmonde-Ortigas',
					'valid' => 'December 30, 2024',
				],
				[
					'name' => 'Microtel by Wyndham (Pampanga)',
					'image' => 'micro.jpg',
					'desc' => 'Discount offer extended to all Our Lady of Fatima University Alumni Members, Employees, and Students and Fatima University Medical Center Employees.',
					'extras' => [
						'20% discount from published rates',
						'10% discount for in-room massage and for in-house guests',
					],
					'valid' => 'July 31, 2024',
				],
				[
					'name' => 'Verde Azul, Morong Bataan',
					'image' => 'verde.jpg',
					'desc' => 'Discount offer extended to all Our Lady of Fatima University Alumni Members, Employees, and Students and Fatima University Medical Center Employees.',
					'extras' => [ '10% discount on Published Room Rates' ],
					'valid' => 'December 31, 2024',
				],
				[
					'name' => 'Timberland Highlands Resort, San Mateo Rizal',
					'image' => 'timber.jpg',
					'desc' => 'Discount offer extended to all Our Lady of Fatima University Alumni Members, Employees, and Students and Fatima University Medical Center Employees.',
					'link_text' => 'Special room rates',
					'link_url' => 'https://bit.ly/Timberland-Room-Rates',
					'valid' => 'December 31, 2024',
				],
				[
					'name' => 'SotoGrande, Katipunan',
					'image' => 'soto.jpg',
					'desc' => 'Discount offer extended to all Our Lady of Fatima University Alumni Members, Employees, and Students and Fatima University Medical Center Employees.',
					'link_text' => 'Special room rates',
					'link_url' => 'https://bit.ly/SotoGrande-Rates',
					'valid' => 'March 31, 2025',
				],
				[
					'name' => 'Shangri-la Hotels and Resorts (All Branches)',
					'image' => 'shang.jpg',
					'desc' => 'Discount offer extended to all Our Lady of Fatima University Alumni Members, Employees, and Students and Fatima University Medical Center Employees.',
					'extras' => [ '10% discount from the best available rates' ],
					'valid' => 'January 31, 2025',
				],
				[
					'name' => 'Savoy Hotel Manila',
					'image' => 'savoy.jpg',
					'desc' => 'Discount offer extended to all Our Lady of Fatima University Alumni Members, Employees, and Students and Fatima University Medical Center Employees.',
					'link_text' => 'Special room rates',
					'link_url' => 'https://bit.ly/Savoy-Hotel-Manila',
					'valid' => 'March 31, 2025',
				],
				[
					'name' => 'Raffles, Makati',
					'image' => 'raff.jpg',
					'desc' => 'Discount offer extended to all Our Lady of Fatima University Alumni Members, Employees, and Students and Fatima University Medical Center Employees.',
					'link_text' => 'Special room rates',
					'link_url' => 'https://bit.ly/Raffles-Room-Rate',
					'valid' => 'December 30, 2024',
				],
			];

			foreach ($partners as $p): ?>
				<?php 
					$extrasJson = !empty($p['extras']) && is_array($p['extras']) ? htmlspecialchars(json_encode($p['extras'])) : '';
					$linkText = htmlspecialchars($p['link_text'] ?? '');
					$linkUrl = htmlspecialchars($p['link_url'] ?? '');
					$valid = htmlspecialchars($p['valid'] ?? '');
				?>
				<div class="relative group cursor-pointer rounded-xl overflow-hidden border border-gray-200 shadow-sm bg-white partner-card"
					data-name="<?php echo htmlspecialchars($p['name']); ?>"
					data-desc="<?php echo htmlspecialchars($p['desc']); ?>"
					data-image="<?php echo htmlspecialchars($p['image']); ?>"
					data-extras='<?php echo $extrasJson; ?>'
					data-link-text="<?php echo $linkText; ?>"
					data-link-url="<?php echo $linkUrl; ?>"
					data-valid="<?php echo $valid; ?>">
					<img src="<?php echo htmlspecialchars($p['image']); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="w-full h-48 sm:h-60 object-cover" />
					<div class="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition"></div>
					<div class="absolute bottom-3 right-3 bg-white/90 text-emerald-700 text-xs px-3 py-1 rounded-full shadow hidden group-hover:block">View details</div>
				</div>
			<?php endforeach; ?>
		</section>

		<!-- Partner Details Modal -->
		<div id="partnerModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
			<div class="bg-white rounded-xl max-w-5xl w-full shadow-lg overflow-hidden max-h-[85vh] flex flex-col md:flex-row relative">
				<!-- Close icon anchored to modal card corner -->
				<button id="pmClose" class="absolute top-2 right-2 bg-white/90 hover:bg-white text-gray-700 rounded-full h-10 w-10 flex items-center justify-center shadow"><i class="fas fa-times"></i></button>
				<!-- Image (Left) -->
				<div class="relative md:w-1/2 bg-white flex items-center justify-center">
					<img id="pmImage" src="" alt="" class="w-full h-full max-h-[85vh] object-contain" />
				</div>
				<!-- Details (Right) -->
				<div class="p-6 overflow-auto md:w-1/2">
					<h2 id="pmName" class="h-elegant text-2xl md:text-3xl font-extrabold text-emerald-800"></h2>
					<p class="text-[12px] uppercase tracking-wider text-gray-500 mt-3">Alumni Benefits</p>
					<p id="pmDesc" class="text-gray-800 mt-2 text-base leading-relaxed"></p>
					<ul id="pmExtras" class="list-disc list-inside text-[15px] text-gray-800 mt-3 space-y-1 hidden"></ul>
					<p id="pmValid" class="text-xs text-gray-500 mt-4"></p>
					<div class="mt-4 flex justify-end">
						<button id="pmCloseBtn" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Close</button>
					</div>
				</div>
			</div>
		</div>
	</main>

	<script>
		document.addEventListener('DOMContentLoaded', function(){

			// Partner modal logic
			const modal = document.getElementById('partnerModal');
			const pmImage = document.getElementById('pmImage');
			const pmName = document.getElementById('pmName');
			const pmDesc = document.getElementById('pmDesc');
			const pmExtras = document.getElementById('pmExtras');
			const pmLinkWrap = document.getElementById('pmLinkWrap');
			const pmLink = document.getElementById('pmLink');
			const pmValid = document.getElementById('pmValid');
			const pmClose = document.getElementById('pmClose');
			const pmCloseBtn = document.getElementById('pmCloseBtn');

			function closeModal(){ modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }
			function openModal(){ modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); }

			document.querySelectorAll('.partner-card').forEach(card => {
				card.addEventListener('click', () => {
					pmImage.src = card.dataset.image || '';
					pmName.textContent = card.dataset.name || '';
					pmDesc.textContent = card.dataset.desc || '';
					pmValid.textContent = card.dataset.valid ? `Valid until: ${card.dataset.valid}` : '';

					// Extras
					pmExtras.innerHTML = '';
					const extrasRaw = card.dataset.extras || '';
					if (extrasRaw) {
						try {
							const arr = JSON.parse(extrasRaw);
							if (Array.isArray(arr) && arr.length) {
								pmExtras.classList.remove('hidden');
								arr.forEach(txt => {
									const li = document.createElement('li');
									li.textContent = txt;
									pmExtras.appendChild(li);
								});
							} else { pmExtras.classList.add('hidden'); }
						} catch(e){ pmExtras.classList.add('hidden'); }
					} else { pmExtras.classList.add('hidden'); }

					// No CTA button required

					openModal();
				});
			});

			// Close handlers
			pmClose.addEventListener('click', closeModal);
			pmCloseBtn.addEventListener('click', closeModal);
			modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
			document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
		});
	</script>
</body>
</html>


