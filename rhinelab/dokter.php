<?php
session_start();
require_once __DIR__ . '/data.php';

// Cek apakah user sudah login sebagai dokter
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (!is_doctor()) {
    if (is_admin()) {
        header('Location: admin.php');
        exit;
    }
    header('Location: login.php');
    exit;
}

$currentDoctorId = intval($_SESSION['doctor_id'] ?? 0);
$doctors = load_data('doctors');
$schedules = load_data('schedules');
$patients = load_data('patients');
$mySchedules = $currentDoctorId ? load_doctor_schedules($currentDoctorId) : [];
$doctorHolidays = $currentDoctorId ? load_doctor_holidays($currentDoctorId) : [];

$doctorId = $currentDoctorId;
$targetDate = isset($_GET['target_date']) ? sanitize($_GET['target_date']) : date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_doctor_holiday') {
        $holidayDate = sanitize($_POST['holiday_date'] ?? '');
        $holidayNote = trim($_POST['holiday_note'] ?? '');

        if ($doctorId <= 0 || $holidayDate === '') {
            $message = 'Tanggal libur tidak valid.';
            $messageType = 'error';
        } else {
            upsert_doctor_holiday($doctorId, $holidayDate, $holidayNote);
            $message = 'Keterangan libur berhasil disimpan.';
            $messageType = 'success';
            $doctorHolidays = load_doctor_holidays($doctorId);
        }
    }
    if ($action === 'add_schedule') {
        $scheduleDate = sanitize($_POST['date'] ?? '');
        $scheduleTime = sanitize($_POST['time'] ?? '');
        $slots = max(1, intval($_POST['slots'] ?? 1));

        if ($doctorId <= 0 || $scheduleDate === '' || $scheduleTime === '') {
            $message = 'Tanggal dan jam jadwal wajib diisi.';
            $messageType = 'error';
        } else {
            insert_schedule($doctorId, $scheduleDate, $scheduleTime, $slots);
            $message = 'Jadwal dokter berhasil ditambahkan.';
            $messageType = 'success';
            $mySchedules = load_doctor_schedules($doctorId);
            $schedules = load_data('schedules');
        }
    }
    if ($action === 'delete_doctor_holiday') {
        $holidayDate = sanitize($_POST['holiday_date'] ?? '');

        if ($doctorId > 0 && $holidayDate !== '') {
            delete_doctor_holiday($doctorId, $holidayDate);
            $message = 'Keterangan libur berhasil dihapus.';
            $messageType = 'success';
            $doctorHolidays = load_doctor_holidays($doctorId);
        } else {
            $message = 'Tanggal libur tidak valid.';
            $messageType = 'error';
        }
    }
}

function format_date_indonesia($date) {
    return date('d F Y', strtotime($date));
}

function get_doctor_name($doctors, $id) {
    $doctor = find_by_id($doctors, $id);
    return $doctor ? $doctor['name'] : '-';
}

function get_appointments($schedules, $patients, $doctorId, $targetDate) {
    $result = [];
    foreach ($schedules as $schedule) {
        if ($schedule['doctor_id'] != $doctorId || $schedule['date'] !== $targetDate) {
            continue;
        }
        $result[] = $schedule;
    }
    usort($result, function ($a, $b) {
        return strcmp($a['time'], $b['time']);
    });
    return $result;
}

function get_patient_list($patients, $scheduleId) {
    return array_values(array_filter($patients, function ($patient) use ($scheduleId) {
        return $patient['schedule_id'] === $scheduleId;
    }));
}

$selectedDoctor = $doctorId ? find_by_id($doctors, $doctorId) : null;
$nextDays = [];
for ($i = 0; $i < 4; $i++) {
    $nextDays[] = date('Y-m-d', strtotime("+$i day", strtotime($targetDate)));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Halaman Dokter</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/style.css" />
</head>
<body>
<div class="container py-5">
    <header class="header-bar d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
        <div>
            <p class="eyebrow text-primary mb-2">Halaman Dokter</p>
            <h1 class="h3 mb-2">Jadwal Pemeriksaan</h1>
            <p class="hero-text text-muted">Lihat pasien yang akan Anda periksa hari ini dan beberapa hari berikutnya.</p>
        </div>
        <nav class="nav gap-2">
            <a class="nav-link px-3 py-2 rounded-3" href="index.php">Beranda</a>
            <a class="nav-link px-3 py-2 rounded-3" href="doctors.php">Profil Dokter</a>
            <a class="nav-link px-3 py-2 rounded-3 active" aria-current="page" href="dokter.php">Jadwal Dokter</a>
            <a class="nav-link px-3 py-2 rounded-3 text-danger fw-bold" href="logout.php">Logout</a>
        </nav>
    </header>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Pilih Tanggal</h2>
            <input type="text" id="scheduleCalendar" class="form-control" placeholder="Pilih tanggal jadwal..." readonly value="<?php echo $targetDate; ?>" />
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Tambah Jadwal Saya</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_schedule" />
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Tanggal</label>
                        <input class="form-control" type="date" name="date" required />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Jam</label>
                        <input class="form-control" type="time" name="time" required />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Jumlah Slot</label>
                        <input class="form-control" type="number" name="slots" min="1" value="4" required />
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" type="submit">Simpan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Keterangan Libur Saya</h2>
            <form method="post" class="mb-4">
                <input type="hidden" name="action" value="save_doctor_holiday" />
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Libur</label>
                        <input class="form-control" type="date" name="holiday_date" value="<?php echo htmlspecialchars($targetDate); ?>" required />
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Keterangan</label>
                        <input class="form-control" type="text" name="holiday_note" placeholder="Contoh: Cuti, kontrol kesehatan, dinas luar" />
                    </div>
                    <div class="col-12">
                        <button class="btn btn-danger" type="submit">Simpan Libur</button>
                    </div>
                </div>
            </form>

            <?php if (empty($doctorHolidays)): ?>
                <p class="muted mb-0">Belum ada keterangan libur yang disimpan.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Keterangan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($doctorHolidays as $holiday): ?>
                                <tr>
                                    <td><?php echo date('d F Y', strtotime($holiday['holiday_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($holiday['note']); ?></td>
                                    <td>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="delete_doctor_holiday" />
                                            <input type="hidden" name="holiday_date" value="<?php echo $holiday['holiday_date']; ?>" />
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-4">Pilih Dokter</h2>
            <form method="get">
                <input type="hidden" name="target_date" value="<?php echo $targetDate; ?>" />
                <div class="mb-3">
                    <label class="form-label">Dokter Aktif</label>
                    <input class="form-control" type="text" value="<?php echo htmlspecialchars($selectedDoctor ? $selectedDoctor['name'] . ' - ' . $selectedDoctor['specialty'] : 'Dokter belum ditemukan'); ?>" readonly />
                </div>
                <button class="btn btn-outline-primary" type="submit" disabled>Lihat Jadwal</button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-3">Jadwal Saya</h2>
            <?php if (empty($mySchedules)): ?>
                <p class="muted mb-0">Belum ada jadwal yang Anda buat.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Jam</th>
                                <th>Slot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mySchedules as $schedule): ?>
                                <tr>
                                    <td><?php echo format_date_indonesia($schedule['date']); ?></td>
                                    <td><?php echo substr($schedule['time'], 0, 5); ?></td>
                                    <td><?php echo (int)$schedule['slots']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selectedDoctor): ?>
        <div class="card">
            <h2>Jadwal <?php echo $selectedDoctor['name']; ?></h2>
            <?php foreach ($nextDays as $day): ?>
                <?php $appointments = get_appointments($schedules, $patients, $doctorId, $day); ?>
                <?php $holiday = get_doctor_holiday($doctorId, $day); ?>
                <div class="card day-card">
                    <div class="day-header">
                        <h3><?php echo format_date_indonesia($day); ?></h3>
                        <span><?php echo $selectedDoctor['specialty']; ?></span>
                    </div>
                    <?php if ($holiday): ?>
                        <div class="alert alert-warning mb-3">
                            <strong>Libur:</strong> <?php echo htmlspecialchars($holiday['note']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (empty($appointments)): ?>
                        <p class="muted"><?php echo $holiday ? 'Dokter sedang libur pada tanggal ini.' : 'Tidak ada jadwal untuk hari ini.'; ?></p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr><th>Jam</th><th>Calon Pasien</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $schedule): ?>
                                    <?php $patientsForSchedule = get_patient_list($patients, $schedule['id']); ?>
                                    <tr>
                                        <td><?php echo substr($schedule['time'], 0, 5); ?></td>
                                        <td>
                                            <?php if (empty($patientsForSchedule)): ?>
                                                <span class="muted">Belum ada pasien</span>
                                            <?php else: ?>
                                                <ul>
                                                    <?php foreach ($patientsForSchedule as $patient): ?>
                                                        <li><?php echo $patient['name']; ?> (<?php echo $patient['phone']; ?>)</li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $holiday ? '<span class="status-full">Libur</span>' : (count($patientsForSchedule) >= $schedule['slots'] ? '<span class="status-full">Penuh</span>' : '<span class="status-free">Tersedia</span>'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <footer>
        <p>Gunakan halaman ini untuk melihat tugas pemeriksaan dokter hari ini dan beberapa hari ke depan.</p>
    </footer>
</div>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    const schedules = <?php echo json_encode($schedules); ?>;
    const today = new Date();
    const scheduledDates = schedules.map(s => s.date);
    
    flatpickr('#scheduleCalendar', {
        mode: 'single',
        defaultDate: today,
        onChange: function(selectedDates) {
            if (selectedDates.length > 0) {
                const formattedDate = selectedDates[0].toISOString().split('T')[0];
                window.location.href = 'dokter.php?target_date=' + formattedDate + (document.querySelector('[name="doctor_id"]')?.value ? '&doctor_id=' + document.querySelector('[name="doctor_id"]').value : '');
            }
        },
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            const dateStr = dayElem.dateObj.toISOString().split('T')[0];
            if (scheduledDates.includes(dateStr)) {
                dayElem.classList.add('has-schedule');
            }
        }
    });
</script>
</body>
</html>
