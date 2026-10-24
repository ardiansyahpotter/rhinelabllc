<?php
session_start();
require_once __DIR__ . '/data.php';

$dayOffset = max(0, isset($_GET['day']) ? intval($_GET['day']) : 0);
$selectedDate = date('Y-m-d', strtotime("+$dayOffset days"));
$selectedScheduleId = (int)($_GET['schedule_id'] ?? 0);
$bookingMessage = '';
$bookingMessageType = 'error';
$queueResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'public_booking') {
        $patientName = trim($_POST['patient_name'] ?? '');
        $patientNik = preg_replace('/\D+/', '', $_POST['patient_nik'] ?? '');
        $patientAddress = trim($_POST['patient_address'] ?? '');
        $patientPhone = trim($_POST['patient_phone'] ?? '');
        $cuppingType = trim($_POST['cupping_type'] ?? '');
        $scheduleId = (int)($_POST['schedule_id'] ?? 0);
        $schedule = $scheduleId > 0 ? get_schedule_by_id($scheduleId) : null;
        $validCuppingTypes = ['Bekam kering', 'Bekam basah', 'Sinanti'];

        if (!$schedule || $patientName === '' || $patientNik === '' || $patientAddress === '' || $patientPhone === '' || !in_array($cuppingType, $validCuppingTypes, true)) {
            $bookingMessage = 'Lengkapi identitas, jenis bekam, dan pilih jadwal yang tersedia.';
        } elseif ($schedule['date'] < date('Y-m-d') || get_doctor_holiday($schedule['doctor_id'], $schedule['date'])) {
            $bookingMessage = 'Jadwal tersebut sudah lewat atau dokter sedang libur.';
        } else {
            try {
                $booking = insert_patient_visit($patientName, $patientNik, $patientAddress, $patientPhone, (int)$schedule['doctor_id'], $scheduleId, $cuppingType);
                $_SESSION['queue_confirmation'] = [
                    'name' => $patientName,
                    'queue_number' => $booking['queue_number'],
                    'date' => $schedule['date'],
                    'time' => $schedule['time'],
                    'doctor' => get_doctor_name(load_data('doctors'), $schedule['doctor_id'])
                ];
                header('Location: index.php?day=' . $dayOffset . '#reservasi');
                exit;
            } catch (Throwable $e) {
                $bookingMessage = $e->getMessage() === 'Jadwal sudah penuh.'
                    ? 'Jadwal sudah penuh. Silakan pilih jadwal lain.'
                    : 'Reservasi gagal disimpan. Periksa data dan coba kembali.';
            }
        }
    } elseif (($_POST['action'] ?? '') === 'lookup_queue') {
        $lookupNik = preg_replace('/\D+/', '', $_POST['lookup_nik'] ?? '');
        $lookupPhone = trim($_POST['lookup_phone'] ?? '');
        if ($lookupNik === '' || $lookupPhone === '') {
            $bookingMessage = 'Masukkan NIK dan nomor telepon yang digunakan saat reservasi.';
        } else {
            $queueResults = find_upcoming_patient_queues($lookupNik, $lookupPhone);
            if (empty($queueResults)) {
                $bookingMessage = 'Tidak ditemukan antrean mendatang untuk kombinasi NIK dan nomor telepon tersebut.';
            } else {
                $bookingMessageType = 'success';
            }
        }
    }
}

$queueConfirmation = $_SESSION['queue_confirmation'] ?? null;
unset($_SESSION['queue_confirmation']);

$doctors = load_data('doctors');
$schedules = load_data('schedules');
$patients = load_data('patients');
$siteContents = load_site_contents();
$doctorHolidays = get_doctor_holidays_for_date($selectedDate);
$bookings = get_schedule_bookings($schedules, $patients);
$allHolidays = load_doctor_holidays();
$holidayKeys = [];
foreach ($allHolidays as $holiday) {
    $holidayKeys[(int)$holiday['doctor_id'] . '|' . $holiday['holiday_date']] = true;
}
$bookableSchedules = array_values(array_filter($schedules, function ($schedule) use ($bookings, $holidayKeys) {
    $key = (int)$schedule['doctor_id'] . '|' . $schedule['date'];
    return $schedule['date'] >= date('Y-m-d')
        && ($bookings[$schedule['id']] ?? 0) < (int)$schedule['slots']
        && !isset($holidayKeys[$key]);
}));
$bookableSchedules = dedupe_latest_schedules($bookableSchedules);

function get_doctor_name($doctors, $id) {
    $doctor = find_by_id($doctors, $id);
    return $doctor ? $doctor['name'] : 'Dokter tidak ditemukan';
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

// Deduplicate schedules so each doctor/date pair keeps only the latest entry
$todaySchedules = dedupe_latest_schedules($todaySchedules);

$availableCount = 0;
foreach ($todaySchedules as $schedule) {
    $booked = $bookings[$schedule['id']] ?? 0;
    $isHoliday = isset($doctorHolidays[$schedule['doctor_id']]);
    if ($booked < $schedule['slots'] && !$isHoliday) {
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
                                <p class="mb-1 text-secondary small">Sisa Tersedia</p>
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
        <!-- <p class="muted mb-3">Jadwal juga akan terhapus apabila sudah melewati tanggal.</p> -->
        <?php if (!empty($doctorHolidays)): ?>
            <div class="alert alert-warning">
                <strong>Catatan libur hari ini:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($doctorHolidays as $holiday): ?>
                        <li><?php echo htmlspecialchars(get_doctor_name($doctors, $holiday['doctor_id'])); ?> - <?php echo htmlspecialchars($holiday['note']); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (empty($todaySchedules)): ?>
            <p class="muted">Belum ada jadwal dokter untuk hari ini.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Dokter</th>
                        <th>Spesialisasi</th>
                        <th>Jam Praktik</th>
                        <th>Menerima pasien</th>
                        <th>Reservasi / Antrean</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todaySchedules as $schedule): ?>
                        <?php
                        $booked = $bookings[$schedule['id']] ?? 0;
                        $holiday = $doctorHolidays[$schedule['doctor_id']] ?? null;
                        $holidayNote = $holiday ? $holiday['note'] : '';
                        $isFull = $booked >= $schedule['slots'];
                        $isBlocked = $holiday !== null;
                        $whatsappText = rawurlencode('Halo Admin, saya ingin reservasi janji temu dengan ' . get_doctor_name($doctors, $schedule['doctor_id']) . ' pada tanggal ' . $selectedDate . ' jam ' . substr($schedule['time'], 0, 5) . '.');
                        ?>
                        <tr>
                            <td><?php echo get_doctor_name($doctors, $schedule['doctor_id']); ?></td>
                            <td><?php echo get_doctor_specialty($doctors, $schedule['doctor_id']); ?></td>
                            <td><?php echo substr($schedule['time'], 0, 5); ?></td>
                            <td>
                                <?php if ($isBlocked): ?>
                                    <span class="status-full">Libur</span> - <?php echo htmlspecialchars($holidayNote !== '' ? $holidayNote : 'Dokter sedang libur'); ?>
                                <?php else: ?>
                                    <?php echo $isFull ? '<span class="status-full">Penuh</span>' : '<span class="status-free">Sisa Tersedia</span>'; ?> (<?php echo $booked; ?>/<?php echo $schedule['slots']; ?>)
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isBlocked): ?>
                                    <span class="muted">Reservasi ditutup</span>
                                <?php elseif ($isFull): ?>
                                    <span class="muted">Reservasi ditutup</span>
                                <?php else: ?>
                                    <a class="btn btn-success btn-sm" href="index.php?day=<?php echo $dayOffset; ?>&amp;schedule_id=<?php echo (int)$schedule['id']; ?>#reservasi">Daftar dan ambil antrean</a>
                                    <a class="btn btn-outline-secondary btn-sm mt-1" href="https://wa.me/<?php echo ADMIN_PHONE; ?>?text=<?php echo $whatsappText; ?>" target="_blank">WhatsApp</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section id="reservasi" class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Reservasi dan Nomor Antrean</h2>
            <p class="text-muted">Isi data untuk mendaftar. Nomor antrean diberikan sesuai urutan pendaftaran pada jadwal yang dipilih.</p>
            <?php if ($bookingMessage): ?>
                <div class="alert alert-<?php echo $bookingMessageType === 'success' ? 'success' : 'danger'; ?>" role="alert"><?php echo htmlspecialchars($bookingMessage); ?></div>
            <?php endif; ?>
            <?php if ($queueConfirmation): ?>
                <div class="alert alert-success queue-ticket" role="status">
                    <h3 class="h5">Reservasi berhasil</h3>
                    <p class="mb-1">Pasien: <strong><?php echo htmlspecialchars($queueConfirmation['name']); ?></strong></p>
                    <p class="mb-1">Nomor antrean: <strong class="queue-number">#<?php echo (int)$queueConfirmation['queue_number']; ?></strong></p>
                    <p class="mb-0"><?php echo htmlspecialchars(date('d-m-Y', strtotime($queueConfirmation['date'])) . ' pukul ' . substr($queueConfirmation['time'], 0, 5) . ' dengan ' . $queueConfirmation['doctor']); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="public_booking" />
                <div class="col-md-6">
                    <label class="form-label" for="patientName">Nama pasien</label>
                    <input class="form-control" id="patientName" name="patient_name" maxlength="150" required />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="patientNik">Nomor KTP / NIK</label>
                    <input class="form-control" id="patientNik" name="patient_nik" inputmode="numeric" maxlength="30" required />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="patientPhone">Nomor telepon</label>
                    <input class="form-control" id="patientPhone" name="patient_phone" type="tel" maxlength="25" required />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="patientAddress">Alamat</label>
                    <input class="form-control" id="patientAddress" name="patient_address" maxlength="1000" required />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="scheduleId">Jadwal dokter</label>
                    <select class="form-select" id="scheduleId" name="schedule_id" required>
                        <option value="">Pilih tanggal dan jam</option>
                        <?php foreach ($bookableSchedules as $schedule): ?>
                            <?php $scheduleDoctor = get_doctor_name($doctors, $schedule['doctor_id']); ?>
                            <option value="<?php echo (int)$schedule['id']; ?>" <?php echo $selectedScheduleId === (int)$schedule['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(date('d-m-Y', strtotime($schedule['date'])) . ' ' . substr($schedule['time'], 0, 5) . ' - ' . $scheduleDoctor); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cuppingType">Jenis bekam</label>
                    <select class="form-select" id="cuppingType" name="cupping_type" required>
                        <option value="">Pilih jenis bekam</option>
                        <option>Bekam kering</option>
                        <option>Bekam basah</option>
                        <option>Sinanti</option>
                    </select>
                </div>
                <div class="col-12"><button class="btn btn-primary" type="submit">Daftar dan dapatkan nomor antrean</button></div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Cek Nomor Antrean</h2>
            <p class="text-muted">Gunakan NIK dan nomor telepon yang sama seperti saat mendaftar.</p>
            <form method="post" class="row g-3 mb-3">
                <input type="hidden" name="action" value="lookup_queue" />
                <div class="col-md-5"><label class="form-label" for="lookupNik">Nomor KTP / NIK</label><input class="form-control" id="lookupNik" name="lookup_nik" inputmode="numeric" required /></div>
                <div class="col-md-5"><label class="form-label" for="lookupPhone">Nomor telepon</label><input class="form-control" id="lookupPhone" name="lookup_phone" type="tel" required /></div>
                <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100" type="submit">Cari antrean</button></div>
            </form>
            <?php if (!empty($queueResults)): ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>Nomor antrean</th><th>Tanggal / Jam</th><th>Dokter</th><th>Pasien</th></tr></thead>
                        <tbody>
                            <?php foreach ($queueResults as $queue): ?>
                                <tr>
                                    <td><strong class="queue-number">#<?php echo (int)$queue['queue_number']; ?></strong></td>
                                    <td><?php echo htmlspecialchars(date('d-m-Y', strtotime($queue['date'])) . ' ' . substr($queue['time'], 0, 5)); ?></td>
                                    <td><?php echo htmlspecialchars($queue['doctor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($queue['name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
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
