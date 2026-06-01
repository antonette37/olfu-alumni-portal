<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: al_homepage.php");
    exit();
}

$dsn = 'mysql:host=localhost;dbname=itcp_db;charset=utf8mb4';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Utility function for single value queries
function getCount($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() ?: 0;
}

// Stats
$totalAlumni = getCount($pdo, "SELECT COUNT(*) FROM itcp");
$employed = getCount($pdo, "SELECT COUNT(*) FROM itcp WHERE employment_status = 'Employed'");
$unemployed = getCount($pdo, "SELECT COUNT(*) FROM itcp WHERE employment_status = 'Unemployed'");
$selfemployed = getCount($pdo, "SELECT COUNT(*) FROM itcp WHERE employment_status = 'Self Employed'");

// Alumni by Year
$alumniByYear = [];
$stmt = $pdo->query("SELECT year_graduated AS year, COUNT(*) AS total FROM itcp WHERE year_graduated IS NOT NULL GROUP BY year ORDER BY year");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $alumniByYear[$row['year']] = $row['total'];
}

// Location Data (using 'present_address' as a location field)
$locationData = [];
$locStmt = $pdo->query("SELECT address AS location, COUNT(*) AS total FROM itcp WHERE address IS NOT NULL GROUP BY address ORDER BY total DESC LIMIT 15");
while ($row = $locStmt->fetch(PDO::FETCH_ASSOC)) {
    $locationData[$row['location']] = $row['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1" name="viewport" />
  <title>Fatima Alumni Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50 text-gray-900 font-sans">
  <main class="pt-12 px-4 sm:px-6 md:px-10 max-w-7xl mx-auto space-y-8">

    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white p-4 rounded shadow text-center">
        <p class="text-sm text-gray-500">Total Alumni</p>
        <p class="text-2xl font-bold text-green-700"><?= $totalAlumni ?></p>
      </div>
      <div class="bg-white p-4 rounded shadow text-center">
        <p class="text-sm text-gray-500">Employed</p>
        <p class="text-2xl font-bold text-green-700"><?= $employed ?></p>
      </div>
      <div class="bg-white p-4 rounded shadow text-center">
        <p class="text-sm text-gray-500">Unemployed</p>
        <p class="text-2xl font-bold text-red-600"><?= $unemployed ?></p>
      </div>
      <div class="bg-white p-4 rounded shadow text-center">
        <p class="text-sm text-gray-500">Self Employed</p>
        <p class="text-2xl font-bold text-blue-600"><?= $selfemployed ?></p>
      </div>
    </section>

    <section class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-semibold text-sm mb-2">Alumni Graduation Year</h2>
        <canvas id="gradYearChart" height="180"></canvas>
      </div>
      <div class="bg-white p-4 rounded shadow">
        <h2 class="font-semibold text-sm mb-2">Employment Status</h2>
        <canvas id="employmentStatusChart" height="180"></canvas>
      </div>
      <div class="bg-white p-4 rounded shadow col-span-1 lg:col-span-2">
        <h2 class="font-semibold text-sm mb-2">Location Distribution</h2>
        <canvas id="locationLineChart" height="200"></canvas>
      </div>
    </section>
  </main>

  <script>
    const gradYearChart = new Chart(document.getElementById('gradYearChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_keys($alumniByYear)) ?>,
        datasets: [{
          label: 'Graduates',
          data: <?= json_encode(array_values($alumniByYear)) ?>,
          backgroundColor: '#15803d'
        }]
      },
      options: {
        responsive: true,
        scales: { y: { beginAtZero: true } }
      }
    });

    const employmentStatusChart = new Chart(document.getElementById('employmentStatusChart'), {
      type: 'pie',
      data: {
        labels: ['Employed', 'Unemployed', 'Self Employed'],
        datasets: [{
          label: 'Employment Status',
          data: [<?= $employed ?>, <?= $unemployed ?>, <?= $selfemployed ?>],
          backgroundColor: ['#16a34a', '#dc2626', '#2563eb']
        }]
      },
      options: {
        responsive: true
      }
    });

    const locationLineChart = new Chart(document.getElementById('locationLineChart'), {
      type: 'line',
      data: {
        labels: <?= json_encode(array_keys($locationData)) ?>,
        datasets: [{
          label: 'Alumni Count',
          data: <?= json_encode(array_values($locationData)) ?>,
          fill: false,
          borderColor: '#0f766e',
          tension: 0.1
        }]
      },
      options: {
        responsive: true
      }
    });
  </script>
</body>
</html>
