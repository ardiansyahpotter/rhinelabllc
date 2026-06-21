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

$doctors = load_data('doctors');
$schedules = load_data('schedules');
$patients = load_data('patients');

$doctorId = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : null;
$targetDate = isset($_GET['target_date']) ? sanitize($_GET['target_date']) : date('Y-m-d');

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
            <h2 class="h5 mb-4">Pilih Dokter</h2>
            <form method="get">
                <input type="hidden" name="target_date" value="<?php echo $targetDate; ?>" />
                <div class="mb-3">
                    <label class="form-label">Nama Dokter</label>
                    <select class="form-select" name="doctor_id" required>
                        <option value="">Pilih dokter</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>" <?php echo $doctorId == $doctor['id'] ? 'selected' : ''; ?>>
                                <?php echo $doctor['name']; ?> - <?php echo $doctor['specialty']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn btn-primary" type="submit">Lihat Jadwal</button>
            </form>
        </div>
    </div>

    <?php if ($selectedDoctor): ?>
        <div class="card">
            <h2>Jadwal <?php echo $selectedDoctor['name']; ?></h2>
            <?php foreach ($nextDays as $day): ?>
                <?php $appointments = get_appointments($schedules, $patients, $doctorId, $day); ?>
                <div class="card day-card">
                    <div class="day-header">
                        <h3><?php echo format_date_indonesia($day); ?></h3>
                        <span><?php echo $selectedDoctor['specialty']; ?></span>
                    </div>
                    <?php if (empty($appointments)): ?>
                        <p class="muted">Tidak ada jadwal untuk hari ini.</p>
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
                                        <td><?php echo count($patientsForSchedule) >= $schedule['slots'] ? '<span class="status-full">Penuh</span>' : '<span class="status-free">Tersedia</span>'; ?></td>
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
