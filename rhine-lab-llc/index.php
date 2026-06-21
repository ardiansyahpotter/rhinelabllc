<?php
require_once __DIR__ . '/data.php';

$dayOffset = max(0, isset($_GET['day']) ? intval($_GET['day']) : 0);
$selectedDate = date('Y-m-d', strtotime("+$dayOffset days"));

$doctors = load_data('doctors');
$schedules = load_data('schedules');
$patients = load_data('patients');
$siteContents = load_site_contents();
$bookings = get_schedule_bookings($schedules, $patients);

function get_doctor_name($doctors, $id) {
    $doctor = find_by_id($doctors, $id);
    return $doctor ? $doctor['name'] : 'Dokter tidak ditemukan';
}

function get_doctor_specialty($doctors, $id) {
    $doctor = find_by_id($doctors, $id);
    return $doctor ? $doctor['specialty'] : 'Umum';
}

function format_date_indonesia($date) {
    return date('d F Y', strtotime($date));
}

$todaySchedules = array_values(array_filter($schedules, function ($schedule) use ($selectedDate) {
    return $schedule['date'] === $selectedDate;
}));

usort($todaySchedules, function ($a, $b) {
    return strcmp($a['time'], $b['time']);
});

$availableCount = 0;
foreach ($todaySchedules as $schedule) {
    $booked = $bookings[$schedule['id']] ?? 0;
    if ($booked < $schedule['slots']) {
        $availableCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($siteContents['site_title'] ?? 'Sistem Klinik Sederhana'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/style.css" />
</head>
<body>
<div class="container py-5">
    <nav class="nav gap-2 mb-4">
        <a class="nav-link px-3 py-2 rounded-3 active fw-bold" aria-current="page" href="index.php">Beranda</a>
        <a class="nav-link px-3 py-2 rounded-3" href="doctors.php">Profil Dokter</a>
    </nav>

    <header class="hero rounded-4 shadow-sm p-5 mb-4 bg-white">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <p class="eyebrow text-primary mb-3"><?php echo htmlspecialchars($siteContents['hero_eyebrow'] ?? 'Selamat Datang di Klinik Sehat'); ?></p>
                <h1 class="display-6 fw-bold mb-3"><?php echo htmlspecialchars($siteContents['hero_title'] ?? 'Reservasi Janji Temu Dokter dengan Mudah'); ?></h1>
                <p class="hero-text text-muted mb-4"><?php echo htmlspecialchars($siteContents['hero_text'] ?? 'Lihat jadwal dokter untuk hari ini, cek ketersediaan slot, dan langsung kirim permintaan reservasi ke admin lewat WhatsApp.'); ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-primary btn-lg" href="#jadwal"><?php echo htmlspecialchars($siteContents['hero_cta_text'] ?? 'Lihat Jadwal'); ?></a>
                    <a class="btn btn-outline-secondary btn-lg" href="https://wa.me/<?php echo ADMIN_PHONE; ?>" target="_blank">Hubungi Admin</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="bg-light rounded-4 p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <p class="text-uppercase text-secondary mb-1 small">Info Cepat</p>
                            <h2 class="h5 mb-0">Klinik dan Reservasi</h2>
                        </div>
                        <span class="badge bg-primary">Minimalis</span>
                    </div>
                    <div class="row gy-3">
                        <div class="col-6">
                            <div class="p-3 bg-white rounded-4 shadow-sm text-center">
                                <p class="mb-1 text-secondary small">Total Dokter</p>
                                <h3 class="h2 mb-0"><?php echo count($doctors); ?></h3>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-white rounded-4 shadow-sm text-center">
                                <p class="mb-1 text-secondary small">Jadwal Hari Ini</p>
                                <h3 class="h2 mb-0"><?php echo count($todaySchedules); ?></h3>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-white rounded-4 shadow-sm text-center">
                                <p class="mb-1 text-secondary small">Slot Tersedia</p>
                                <h3 class="h2 mb-0"><?php echo $availableCount; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="hero-panel">
            <div class="panel-card">
                <p>Total Dokter</p>
                <strong><?php echo count($doctors); ?></strong>
            </div>
            <div class="panel-card">
                <p>Jadwal Hari Ini</p>
                <strong><?php echo count($todaySchedules); ?></strong>
            </div>
            <div class="panel-card">
                <p>Slot Tersedia</p>
                <strong><?php echo $availableCount; ?></strong>
            </div>
        </div>
    </header>

    <section class="card shadow-sm p-4 mb-4">
        <h3 class="h5 mb-3">Pilih Tanggal Jadwal</h3>
        <input type="text" id="scheduleCalendar" class="form-control" placeholder="Pilih tanggal jadwal..." readonly />
    </section>

    <section id="jadwal" class="card">
        <h2>Jadwal Dokter pada <?php echo format_date_indonesia($selectedDate); ?></h2>
        <?php if (empty($todaySchedules)): ?>
            <p class="muted">Belum ada jadwal dokter untuk hari ini.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Dokter</th>
                        <th>Spesialisasi</th>
                        <th>Jam</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todaySchedules as $schedule): ?>
                        <?php
                        $booked = $bookings[$schedule['id']] ?? 0;
                        $isFull = $booked >= $schedule['slots'];
                        $whatsappText = rawurlencode('Halo Admin, saya ingin reservasi janji temu dengan ' . get_doctor_name($doctors, $schedule['doctor_id']) . ' pada tanggal ' . $selectedDate . ' jam ' . substr($schedule['time'], 0, 5) . '.');
                        ?>
                        <tr>
                            <td><?php echo get_doctor_name($doctors, $schedule['doctor_id']); ?></td>
                            <td><?php echo get_doctor_specialty($doctors, $schedule['doctor_id']); ?></td>
                            <td><?php echo substr($schedule['time'], 0, 5); ?></td>
                            <td><?php echo $isFull ? '<span class="status-full">Penuh</span>' : '<span class="status-free">Tersedia</span>'; ?> (<?php echo $booked; ?>/<?php echo $schedule['slots']; ?>)</td>
                            <td>
                                <?php if ($isFull): ?>
                                    <span class="muted">Reservasi ditutup</span>
                                <?php else: ?>
                                    <a class="btn btn-success btn-sm" href="https://wa.me/<?php echo ADMIN_PHONE; ?>?text=<?php echo $whatsappText; ?>" target="_blank">Reservasi via WhatsApp</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="card schedule-nav shadow-sm p-4 mb-4">
        <h3 class="h5 mb-3">Periksa jadwal hari lain</h3>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="index.php?day=<?php echo max(0, $dayOffset - 1); ?>"><?php echo $dayOffset > 0 ? 'Hari Sebelumnya' : 'Hari Ini'; ?></a>
            <a class="btn btn-outline-secondary" href="index.php?day=<?php echo $dayOffset + 1; ?>">Lihat Hari Berikutnya</a>
        </div>
    </section>

    <footer class="text-center py-4 mt-4 text-muted">
        <p class="mb-0"><?php echo htmlspecialchars($siteContents['footer_text'] ?? 'Untuk reservasi, klik tombol WhatsApp dan kirim pesan ke admin klinik.'); ?></p>
    </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    // Ambil semua tanggal yang memiliki jadwal
    const schedules = <?php echo json_encode($schedules); ?>;
    const today = new Date();
    const scheduledDates = schedules.map(s => s.date);
    
    // Konfigurasi Flatpickr
    flatpickr('#scheduleCalendar', {
        mode: 'single',
        defaultDate: today,
        minDate: today,
        onChange: function(selectedDates) {
            if (selectedDates.length > 0) {
                const formattedDate = selectedDates[0].toISOString().split('T')[0];
                const dayDifference = Math.floor((selectedDates[0] - today) / (1000 * 60 * 60 * 24));
                window.location.href = 'index.php?day=' + dayDifference;
            }
        },
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            // Highlight tanggal dengan jadwal
            const dateStr = dayElem.dateObj.toISOString().split('T')[0];
            if (scheduledDates.includes(dateStr)) {
                dayElem.classList.add('has-schedule');
            }
        }
    });
</script>
</body>
</html>
