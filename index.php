<?php
session_start();

// ============================================================
// 1. BACKEND CONTROLLER & DATA HANDLER
// ============================================================

// --- 1.A. CONFIGURATION & CONSTANTS ---
date_default_timezone_set('Asia/Jakarta');
$feedback_file = 'feedback_data.csv';
$mood_file     = 'mood_data.csv'; 
$app_name      = "InsightSpace"; 
$forum_file    = 'forum_data.json';   // [BARU] File JSON untuk Data Forum
$booking_file  = 'booking_data.json'; // [BARU] File JSON untuk Data Booking

// --- [BARU] HELPER FUNCTIONS UNTUK JSON ---
function getJsonData($filename, $default = []) {
    if (!file_exists($filename)) return $default;
    $data = json_decode(file_get_contents($filename), true);
    return is_array($data) ? $data : $default;
}
function saveJsonData($filename, $data) {
    file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
}

// --- 1.B. LOGOUT HANDLER ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// --- 1.C. FEEDBACK HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_feedback') {
    $name       = htmlspecialchars($_POST['name'] ?? 'Anonymous');
    $rating     = htmlspecialchars($_POST['rating'] ?? '0');
    $feature    = htmlspecialchars($_POST['feature'] ?? '-');
    $suggestion = str_replace(["\r", "\n"], ' ', htmlspecialchars($_POST['suggestion'] ?? '-'));
    $date       = date("Y-m-d H:i:s");

    $csvLine = "\"$date\",\"$name\",\"$rating\",\"$feature\",\"$suggestion\"\n";
    if (!file_exists($feedback_file)) {
        file_put_contents($feedback_file, "\"Date\",\"Name\",\"Rating\",\"Favorite Feature\",\"Suggestion\"\n");
    }
    file_put_contents($feedback_file, $csvLine, FILE_APPEND);

    $_SESSION['show_thankyou_popup'] = true;
    header("Location: index.php");
    exit;
}

// --- 1.C.2. MOOD HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_mood') {
    $user = $_SESSION['user_name'] ?? 'Anonymous';
    $mood = htmlspecialchars($_POST['mood'] ?? 'Neutral');
    $date = date("Y-m-d H:i:s");
    
    $csvLine = "\"$date\",\"$user\",\"$mood\"\n";
    if (!file_exists($mood_file)) {
        file_put_contents($mood_file, "\"Date\",\"User\",\"Mood\"\n");
    }
    file_put_contents($mood_file, $csvLine, FILE_APPEND);
    
    $_SESSION['mood_submitted'] = true;
    header("Location: index.php");
    exit;
}

// --- [BARU] 1.C.3. BOOKING ROOM HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_room') {
    $room = $_POST['room_id'];
    $slot = $_POST['time_slot'];
    $user = $_SESSION['user_name'];
    $date = date('Y-m-d'); // Booking untuk hari ini

    $bookings = getJsonData($booking_file);
    
    // Struktur Data: [ '2025-10-27' => [ 'room1' => [ '09:00' => 'Bernadus' ] ] ]
    if (!isset($bookings[$date])) $bookings[$date] = [];
    if (!isset($bookings[$date][$room])) $bookings[$date][$room] = [];

    // Cek ketersediaan
    if (!isset($bookings[$date][$room][$slot])) {
        $bookings[$date][$room][$slot] = $user;
        saveJsonData($booking_file, $bookings);
        $_SESSION['notif_msg'] = "Berhasil booking ruangan $room jam $slot!";
    } else {
        $_SESSION['notif_err'] = "Gagal! Slot sudah terisi orang lain.";
    }
    header("Location: index.php");
    exit;
}

// --- [BARU] 1.C.4. FORUM POST HANDLER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_forum') {
    $title = htmlspecialchars($_POST['title']);
    $cat   = htmlspecialchars($_POST['category']);
    $user  = $_SESSION['user_name'];
    
    // Tentukan warna badge berdasarkan kategori
    $badge_color = 'bg-secondary';
    if($cat == 'Network') $badge_color = 'bg-primary';
    if($cat == 'Lifestyle') $badge_color = 'bg-success';
    if($cat == 'Helpdesk') $badge_color = 'bg-danger';
    if($cat == 'General') $badge_color = 'bg-info';

    $topics = getJsonData($forum_file);
    
    $newTopic = [
        "id" => uniqid(),
        "u" => $user,
        "av" => "https://ui-avatars.com/api/?name=".urlencode($user)."&background=random", // Avatar dinamis
        "t" => $title,
        "cat" => $cat,
        "c_bg" => $badge_color,
        "l" => 0,
        "c" => 0,
        "tm" => "Baru saja"
    ];
    
    // Tambah ke paling atas array
    array_unshift($topics, $newTopic); 
    saveJsonData($forum_file, $topics);
    
    header("Location: index.php");
    exit;
}

// --- 1.D. LOGIN HANDLER ---
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'do_login') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    // CREDENTIALS: insight / admin
    if ($user === 'fiberstar' && $pass === 'fs123') { 
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_name']      = "Bernadus Bayu";
        $_SESSION['user_dept']      = "Innovation Dept";
        $_SESSION['user_nik']       = "IS-2025-001"; 
        $_SESSION['user_email']     = "bernadus@insightspace.id"; 
        $_SESSION['show_welcome_popup'] = true;
        
        $_SESSION['login_streak'] = rand(3, 15); 
        $badges = [];
        $hour = date('H');
        if ($hour < 8) { $badges[] = ["icon" => "fas fa-coffee", "color" => "text-warning", "title" => "Early Bird", "bg" => "bg-warning-subtle"]; } 
        else if ($hour > 18) { $badges[] = ["icon" => "fas fa-moon", "color" => "text-info", "title" => "Night Owl", "bg" => "bg-info-subtle"]; }
        $badges[] = ["icon" => "fas fa-lightbulb", "color" => "text-primary", "title" => "Top Innovator", "bg" => "bg-primary-subtle"];
        $_SESSION['user_badges'] = $badges;

        header("Location: index.php");
        exit;
    } else {
        $login_error = 'Username atau Password salah!';
    }
}

// --- 1.E. AJAX HANDLER (AI & SEARCH) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!in_array($_POST['action'], ['do_login', 'submit_feedback', 'submit_mood', 'book_room', 'post_forum'])) {
        header('Content-Type: application/json');

        if ($_POST['action'] === 'chat_gemini') {
            $apiKey = "AIzaSyB8TvsSMeM_Mip7LJLkBfI2AgSIObK1E10"; 
            $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-preview-09-2025:generateContent?key=" . $apiKey;
            $userMessage = $_POST['message'] ?? '';
            $systemPrompt = "Kamu adalah 'InsightBot', asisten AI profesional dari InsightSpace. Misi kami adalah Ruang Inspirasi, Kolaborasi, dan Solusi. Jawab ramah, singkat, padat. Gunakan Bold (**).";
            
            $payload = [ "contents" => [ ["parts" => [["text" => $systemPrompt . "\n\nUser: " . $userMessage]]] ] ];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                $reply = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, saya tidak mengerti.';
                echo json_encode(['reply' => $reply]);
            } else {
                echo json_encode(['reply' => "⚠️ Sistem AI sedang sibuk."]);
            }
            exit;
        }

        if ($_POST['action'] === 'search_employee') {
            $db = [
                ["nik"=>"IS-2025-001", "name"=>"Bernadus Bayu", "dept"=>"Innovation", "pos"=>"Lead Innovator", "email"=>"bernadus@insightspace.id", "img"=>"https://images.unsplash.com/photo-1560250097-0b93528c311a?w=200"],
                ["nik"=>"IS-2025-002", "name"=>"Steven William Y", "dept"=>"Tech Labs", "pos"=>"Senior Engineer", "email"=>"steven.y@insightspace.id", "img"=>"https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=200"],
                ["nik"=>"IS-2025-003", "name"=>"Dhea Anjar", "dept"=>"Creative", "pos"=>"Content Lead", "email"=>"dhea@insightspace.id", "img"=>"https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=200"],
                ["nik"=>"IS-2025-004", "name"=>"Budi Santoso", "dept" => "Ops", "pos"=>"Field Manager", "email"=>"budi@insightspace.id", "img"=>"https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=200"],
            ];
            $term = strtolower($_POST['term'] ?? '');
            $res = [];
            if (!empty($term)) {
                foreach ($db as $d) {
                    if (strpos(strtolower($d['name']), $term) !== false || strpos(strtolower($d['dept']), $term) !== false || strpos(strtolower($d['pos']), $term) !== false) {
                        $res[] = $d;
                    }
                }
            }
            echo json_encode(['results' => $res]);
            exit;
        }
    }
}

// ============================================================
// 2. VIEW: LOGIN PAGE
// ============================================================
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - InsightSpace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --brand-color: #00A9B4; --brand-dark: #008C95; }
        body { font-family: 'Poppins', sans-serif; background-color: #fff; height: 100vh; overflow: hidden; position: relative; transition: background-color 0.3s; }
        
        /* Dark Mode for Login Page */
        [data-theme="dark"] body { background-color: #121212; color: #e0e0e0; }
        [data-theme="dark"] .login-form-area { background-color: #1e1e1e !important; color: #e0e0e0; }
        [data-theme="dark"] .form-control { background-color: #2d2d2d; border-color: #444; color: #fff; }
        [data-theme="dark"] .form-control::placeholder { color: #888; }
        [data-theme="dark"] .text-muted { color: #a0a0a0 !important; }
        [data-theme="dark"] .text-dark { color: #fff !important; }
        [data-theme="dark"] .input-group-text { background-color: #2d2d2d; border-color: #444; color: #888; }

        .login-brand-area { 
            background: linear-gradient(135deg, #00A9B4 0%, #005f6b 100%), url('https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=1920&q=80'); 
            background-blend-mode: multiply; background-size: cover; background-position: center; 
            height: 100vh; display: flex; flex-direction: column; justify-content: center; padding: 60px; color: white; position: relative; 
        }
        .login-brand-area::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('https://www.transparenttextures.com/patterns/cubes.png'); opacity: 0.15; }
        .login-form-area { height: 100vh; display: flex; flex-direction: column; justify-content: center; padding: 0 80px; background: #fff; position: relative; transition: background-color 0.3s; }
        .input-group-text { background: transparent; border-right: none; border-color: #eee; color: #999; }
        .form-control { border-left: none; border-color: #eee; padding: 12px 15px; font-size: 0.95rem; transition: 0.3s; }
        .form-control:focus { box-shadow: none; border-color: var(--brand-color); }
        .input-group:focus-within .input-group-text { border-color: var(--brand-color); color: var(--brand-color); }
        
        .btn-login { 
            background: linear-gradient(to right, #00A9B4, #00d4e3); border: none; color: white; padding: 12px; 
            font-weight: 600; letter-spacing: 0.5px; border-radius: 10px; transition: all 0.3s ease; 
            box-shadow: 0 10px 20px -5px rgba(0, 169, 180, 0.4); 
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 15px 25px -5px rgba(0, 169, 180, 0.5); color: white; }
        
        .brand-quote { font-size: 2.5rem; font-weight: 700; line-height: 1.3; margin-bottom: 20px; text-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .animate-up { animation: fadeInUp 0.8s cubic-bezier(0.2, 0.8, 0.2, 1); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        
        .btn-fullscreen-float {
            position: absolute; top: 20px; right: 20px; width: 45px; height: 45px; border-radius: 50%;
            background: white; border: 1px solid #eee; color: #666; display: flex; align-items: center;
            justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.08); transition: all 0.3s ease;
            z-index: 1050; cursor: pointer;
        }
        .btn-fullscreen-float:hover { transform: scale(1.1); color: var(--brand-color); }
        [data-theme="dark"] .btn-fullscreen-float { background: #333; border-color: #444; color: #fff; }

        @media (max-width: 768px) { .login-brand-area { display: none; } .login-form-area { padding: 30px; } }
    </style>
</head>
<body>
    <button class="btn-fullscreen-float" onclick="toggleFullscreenLogin()" title="Toggle Fullscreen"><i class="fas fa-expand" id="iconFullscreenLogin"></i></button>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <div class="col-lg-7 d-none d-lg-flex login-brand-area">
                <div class="animate-up">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="bg-white rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                            <i class="fas fa-lightbulb fa-2x" style="color: #00A9B4;"></i>
                        </div>
                        <h2 class="m-0 fw-bold">InsightSpace</h2>
                    </div>
                    <h1 class="brand-quote">Ruang Inspirasi,<br>Kolaborasi, dan Solusi.</h1>
                    <p class="lead opacity-75">Empowering your productivity with integrated digital solutions.</p>
                </div>
            </div>
            <div class="col-lg-5 login-form-area">
                <div class="w-100 animate-up" style="max-width: 400px; margin: 0 auto;">
                    <img src="jpeg.png" height="80" alt="InsightSpace" class="mb-4 object-fit-contain" onerror="this.src='https://placehold.co/200x80/00A9B4/white?text=InsightSpace';">
                    <h3 class="fw-bold text-dark mb-1">Welcome Back! 👋</h3>
                    <p class="text-muted mb-4">Please enter your credentials.</p>
                    <?php if($login_error): ?>
                        <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger d-flex align-items-center gap-2 mb-4 rounded-3">
                            <i class="fas fa-exclamation-circle"></i> <small><?php echo $login_error; ?></small>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="do_login">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">USERNAME</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="far fa-user"></i></span>
                                <input type="text" class="form-control" name="username" placeholder=Username required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">PASSWORD</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" name="password" id="passwordInput" placeholder=Password required>
                                <button class="btn btn-light border border-start-0 text-muted" type="button" onclick="togglePass()" style="border-color: #eee!important;">
                                    <i class="far fa-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-login w-100 mb-3">LOGIN TO PORTAL</button>
                        <div class="text-center mt-3">
                            <a href="#" class="small text-muted text-decoration-none" onclick="toggleTheme()">
                                <i class="fas fa-adjust me-1"></i> Toggle Theme
                            </a>
                        </div>
                        <p class="text-center text-muted small mt-4">&copy; 2025 InsightSpace Corp.<br>All rights reserved.</p>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        function togglePass() {
            const passInput = document.getElementById('passwordInput'); const icon = document.getElementById('eyeIcon');
            if (passInput.type === 'password') { passInput.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); } 
            else { passInput.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
        }
        function toggleFullscreenLogin() {
            const elem = document.documentElement;
            if (!document.fullscreenElement) { elem.requestFullscreen(); } else { document.exitFullscreen(); }
        }
        function toggleTheme() {
            const html = document.documentElement;
            if (html.getAttribute('data-theme') === 'dark') {
                html.removeAttribute('data-theme');
                localStorage.setItem('fs_theme', 'light');
            } else {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('fs_theme', 'dark');
            }
        }
        if(localStorage.getItem('fs_theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
</body>
</html>
<?php exit; }

// ============================================================
// 3. VIEW: MAIN DASHBOARD
// ============================================================

// --- 3.A. DATA PREPARATION ---
$user_name  = $_SESSION['user_name'] ?? 'Insight Crew';
$user_dept  = $_SESSION['user_dept'] ?? 'Innovation';
$user_nik   = $_SESSION['user_nik']  ?? 'IS-000';
$user_email = $_SESSION['user_email'] ?? 'user@insightspace.id';
$user_photo = "https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-4.0.3&auto=format&fit=crop&w=256&q=80"; 
$login_streak = $_SESSION['login_streak'] ?? 1;
$user_badges = $_SESSION['user_badges'] ?? [];

$notifications = [
    ["from" => "HR System", "msg" => "Slip gaji bulan ini tersedia.", "time" => "10m ago", "unread" => true, "color" => "bg-primary"],
    ["from" => "Tech Labs", "msg" => "Maintenance server pukul 22:00 WIB.", "time" => "1h ago", "unread" => true, "color" => "bg-danger"],
    ["from" => "InsightBot", "msg" => "Daily summary is ready.", "time" => "5h ago", "unread" => false, "color" => "bg-success"]
];
$unread_count = 0; foreach($notifications as $n) if($n['unread']) $unread_count++;

$hr_stats = [
    ["label" => "Sisa Cuti", "val" => "12 Hari", "icon" => "fas fa-plane-departure", "col" => "text-primary", "bg" => "bg-primary-subtle"],
    ["label" => "Status Klaim", "val" => "Diproses", "icon" => "fas fa-file-invoice-dollar", "col" => "text-warning", "bg" => "bg-warning-subtle"],
    ["label" => "Benefit", "val" => "Active", "icon" => "fas fa-heartbeat", "col" => "text-danger", "bg" => "bg-danger-subtle"],
    ["label" => "Daily Activity", "val" => "On Track", "icon" => "fas fa-clipboard-check", "col" => "text-success", "bg" => "bg-success-subtle"],
];

$hse_updates = [
    ["title" => "Tips Menjaga Kesehatan Saat Musim Pancaroba", "img" => "https://swy.co.id/all/portal/HSE1.jpg?w=600", "full" => "https://swy.co.id/all/portal/HSE1.jpg?w=1200"],
    ["title" => "Bahaya Horseplay di Tempat Kerja", "img" => "https://swy.co.id/all/portal/HSE2.jpg?w=600", "full" => "https://swy.co.id/all/portal/HSE2.jpg?w=1200"],
    ["title" => "6 Penyebab Muncul Rasa Nyeri pada Bahu", "img" => "https://swy.co.id/all/portal/HSE3.jpg?w=600", "full" => "https://swy.co.id/all/portal/HSE3.jpg?w=1200"]
];

// [MODIFIED] LOAD FORUM FROM JSON
$forum_topics = getJsonData($forum_file);
// Default data jika kosong
if (empty($forum_topics)) {
    $forum_topics = [
        ["id" => 1, "u" => "System", "av" => "https://ui-avatars.com/api/?name=System", "t" => "Selamat Datang di Forum!", "cat" => "General", "c_bg" => "bg-success", "l" => 10, "c" => 0, "tm" => "Now"],
        ["id" => 2, "u" => "Yuda Rismawan", "av" => "https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100", "t" => "Best Practice Config Mikrotik CCR2004?", "cat" => "Network", "c_bg" => "bg-primary", "l" => 12, "c" => 5, "tm" => "2h ago"],
        ["id" => 3, "u" => "Dhea Anjar", "av" => "https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=100", "t" => "Rekomendasi tempat makan siang enak dekat kantor?", "cat" => "Lifestyle", "c_bg" => "bg-success", "l" => 45, "c" => 12, "tm" => "4h ago"]
    ];
}

// [BARU] LOAD BOOKING DATA
$bookings_db = getJsonData($booking_file);
$today_date = date('Y-m-d');
$today_bookings = $bookings_db[$today_date] ?? [];
$meeting_rooms = [
    'R01' => ['name' => 'Innovation Room', 'cap' => '10 Pax'],
    'R02' => ['name' => 'Focus Room', 'cap' => '4 Pax'],
    'R03' => ['name' => 'Townhall', 'cap' => '50 Pax']
];
$time_slots = ['09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00'];

$job_vacancies = [
    ["title" => "Network Engineer", "loc" => "Jakarta", "exp" => "Min. 2 Years", "type" => "Fulltime"],
    ["title" => "Sales Executive", "loc" => "Surabaya", "exp" => "Fresh Grad", "type" => "Contract"],
    ["title" => "Java Developer", "loc" => "Remote", "exp" => "Min. 3 Years", "type" => "Fulltime"]
];

$sop_list = [
    ["title" => "SOP Pengajuan Cuti Online", "date" => "Updated 2 days ago", "type" => "PDF"],
    ["title" => "Kebijakan WFH & Remote Working", "date" => "Updated 1 week ago", "type" => "DOC"],
    ["title" => "Panduan Keamanan Informasi (ISO)", "date" => "Updated 1 month ago", "type" => "PDF"]
];

$ig_feed = [
    ["img" => "https://images.unsplash.com/photo-1511632765486-a01980e01a18?w=200&q=80", "likes" => 120],
    ["img" => "https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=200&q=80", "likes" => 89],
    ["img" => "https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=200&q=80", "likes" => 245]
];

$social_shorts = [
    ["icon" => "fab fa-tiktok", "col" => "text-dark", "bg" => "bg-light", "views" => "1.2K", "img" => "https://images.unsplash.com/photo-1531482615713-2afd69097998?auto=format&fit=crop&w=300&q=80"],
    ["icon" => "fab fa-youtube", "col" => "text-danger", "bg" => "bg-light", "views" => "850", "img" => "https://images.unsplash.com/photo-1573164713714-d95e436ab8d6?auto=format&fit=crop&w=300&q=80"],
    ["icon" => "fab fa-instagram", "col" => "text-warning", "bg" => "bg-light", "views" => "2.4M", "img" => "https://images.unsplash.com/photo-1600880292203-757bb62b4baf?auto=format&fit=crop&w=300&q=80"],
    ["icon" => "fab fa-tiktok", "col" => "text-dark", "bg" => "bg-light", "views" => "500K", "img" => "https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=300&q=80"],
];

$learning_hub = [
    ["course" => "Cyber Security", "progress" => 80, "icon" => "fas fa-shield-alt", "col" => "text-danger"],
    ["course" => "Fiber Optic 101", "progress" => 45, "icon" => "fas fa-network-wired", "col" => "text-primary"],
    ["course" => "Leadership", "progress" => 100, "icon" => "fas fa-user-tie", "col" => "text-warning"],
    ["course" => "Project Management", "progress" => 20, "icon" => "fas fa-tasks", "col" => "text-info"],
    ["course" => "Data Analytics", "progress" => 60, "icon" => "fas fa-chart-bar", "col" => "text-success"],
    ["course" => "HSE Safety", "progress" => 10, "icon" => "fas fa-hard-hat", "col" => "text-secondary"],
    ["course" => "Cloud Computing", "progress" => 30, "icon" => "fas fa-cloud", "col" => "text-primary"],
    ["course" => "Public Speaking", "progress" => 90, "icon" => "fas fa-microphone-alt", "col" => "text-danger"]
];

$leaderboard = [
    ["rank" => 1, "name" => "Dhea Anjar", "dept" => "MKT", "score" => 1250, "img" => "https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=100&q=80"],
    ["rank" => 2, "name" => "Steven William Y", "dept" => "ENG", "score" => 1100, "img" => $user_photo],
    ["rank" => 3, "name" => "Bernadus Bayu", "dept" => "SAL", "score" => 980, "img" => "https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&q=80"]
];

$quick_apps = [
    ["name" => "Workplace", "is_img" => false, "icon" => "fas fa-briefcase", "bg" => "bg-primary-subtle", "text" => "text-primary", "link" => "#"],
    ["name" => "Analytics", "is_img" => false, "icon" => "fas fa-chart-pie", "bg" => "bg-success-subtle", "text" => "text-success", "link" => "#"],
    ["name" => "Cloud", "is_img" => false, "icon" => "fas fa-cloud", "bg" => "bg-info-subtle", "text" => "text-info", "link" => "#"],
    ["name" => "Helpdesk", "is_img" => false, "icon" => "fas fa-headset", "bg" => "bg-warning-subtle", "text" => "text-warning", "link" => "#"],
    ["name" => "CRM", "is_img" => false, "icon" => "fas fa-users", "bg" => "bg-danger-subtle", "text" => "text-danger", "link" => "#"],
    ["name" => "Files", "is_img" => false, "icon" => "fas fa-folder-open", "bg" => "bg-primary-subtle", "text" => "text-primary", "link" => "#"],
    ["name" => "Calendar", "is_img" => false, "icon" => "fas fa-calendar-alt", "bg" => "bg-success-subtle", "text" => "text-success", "link" => "#"],
    ["name" => "Mail", "is_img" => false, "icon" => "fas fa-envelope", "bg" => "bg-info-subtle", "text" => "text-info", "link" => "#"]
];

$news_list = [
    ["title" => "InsightSpace Launching Day", "cat" => "Event", "img" => "https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=800"], 
    ["title" => "Kolaborasi Lintas Divisi", "cat" => "Culture", "img" => "https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=400"],
    ["title" => "Teknologi AI Terbaru", "cat" => "Tech", "img" => "https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=400"],
    ["title" => "Townhall Q4 2025", "cat" => "Management", "img" => "https://images.unsplash.com/photo-1600880292203-757bb62b4baf?w=400"],
    ["title" => "Safety First Campaign", "cat" => "HSE", "img" => "https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=400"]
];

$birthdays = [
    ["name" => "Steven William Yudansha", "date" => "18 Nov", "is_today" => true, "role" => "Internal Audit"],
    ["name" => "Dhea Anjar Sari", "date" => "19 Nov", "is_today" => false, "role" => "Marketing"],
    ["name" => "Dwi Wulan Sari", "date" => "19 Nov", "is_today" => false, "role" => "Finance"],
    ["name" => "Mohamad Rahmat", "date" => "19 Nov", "is_today" => false, "role" => "IT Support"],
    ["name" => "Ilham Faqih R.", "date" => "20 Nov", "is_today" => false, "role" => "Developer"]
];
$new_borns = [ ["name" => "Rina Melati", "dept" => "Finance", "baby" => "Putri Pertama", "date" => "15 Nov"], ["name" => "Budi Santoso", "dept" => "Network", "baby" => "Putra Kedua", "date" => "12 Nov"] ];
$new_joiners = [ ["name" => "Alfiano Vian", "dept" => "Corp Sales", "pos" => "Sales Manager", "date" => "13 Nov", "initials" => "AV"] ];

$active_quiz = [ "title" => "Kuis Inovasi Q4", "desc" => "Ikuti kuis dan menangkan hadiah!", "link" => "#", "points" => "100 Poin" ];
$newsletter_list = [ ["vol" => "Vol 74", "month" => "Dec 2025", "file" => "PDF"], ["vol" => "Vol 73", "month" => "Nov 2025", "file" => "PDF"] ];

// --- [NEW] vCard Generation Logic ---
$vcard = "BEGIN:VCARD\nVERSION:3.0\nFN:$user_name\nORG:InsightSpace\nTITLE:$user_dept\nTEL;TYPE=WORK,VOICE:021-12345678\nEMAIL:$user_email\nEND:VCARD";
$my_qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($vcard);

// Ticker Data Logic
$ticker_items = [];
foreach($news_list as $n) { $ticker_items[] = '<span class="mx-3"><i class="fas fa-lightbulb text-warning me-1"></i> ' . $n['title'] . '</span>'; }
foreach($birthdays as $b) { if($b['is_today']) { array_unshift($ticker_items, '<span class="mx-3 text-danger fw-bold"><i class="fas fa-birthday-cake me-1"></i> HAPPY BIRTHDAY ' . strtoupper($b['name']) . '!</span>'); } }
$ticker_html = implode(" | ", $ticker_items);
$popup_image_url = "https://swy.co.id/all/portal/CORPORATEVALUE.jpg"; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InsightSpace Enterprise Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

    <style>
        :root { 
            /* --- REBRANDING COLOR UPDATE (TEAL/TOSCA) --- */
            --fs-orange: #00A9B4;       /* Main Brand Color (Teal) */
            --fs-orange-hover: #008C95; /* Darker Shade */
            --fs-orange-light: #E0F7FA; /* Very Light Shade for backgrounds */
            
            --fs-bg: #f4f7fc; --fs-text: #2d3748; 
            --card-radius: 16px; 
            --shadow-subtle: 0 6px 20px rgba(0,0,0,0.03); 
            --shadow-hover: 0 15px 35px rgba(0,169,180,0.08); /* Shadow tinted with brand color */
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); 
        }

        /* =========================================
           COMPREHENSIVE DARK MODE FIXES (FINAL)
           ========================================= */
        
        /* 1. GLOBAL BACKGROUND FIX (Menghindari sisi putih) */
        [data-theme="dark"] body, 
        [data-theme="dark"] html { 
            background-color: #121212 !important; 
            color: #e0e0e0 !important; 
            height: 100%;
        }

        /* 2. FORCE OVERRIDES FOR UTILITY CLASSES */
        /* Ini mengatasi class seperti .bg-white atau .bg-light yang ada di HTML */
        [data-theme="dark"] .bg-white, 
        [data-theme="dark"] .bg-light { 
            background-color: #1e1e1e !important; 
            color: #e0e0e0 !important;
        }
        
        /* 3. TEXT COLOR FIXES */
        [data-theme="dark"] .text-dark { color: #f8f9fa !important; }
        [data-theme="dark"] .text-muted { color: #a0a0a0 !important; }
        
        /* 4. COMPONENT BACKGROUNDS & BORDERS */
        [data-theme="dark"] .card, 
        [data-theme="dark"] .navbar, 
        [data-theme="dark"] .modal-content,
        [data-theme="dark"] .dropdown-menu,
        [data-theme="dark"] .list-group-item,
        [data-theme="dark"] .card-header-clean,
        [data-theme="dark"] .ticker-wrap,
        [data-theme="dark"] .chat-window,
        [data-theme="dark"] .emp-search-header,
        [data-theme="dark"] .spotlight-input-group,
        [data-theme="dark"] footer {
            background-color: #1e1e1e !important;
            color: #e0e0e0 !important;
            border-color: #333 !important;
        }

        /* 5. INPUTS & FORMS */
        [data-theme="dark"] .form-control, 
        [data-theme="dark"] .input-group-text,
        [data-theme="dark"] .search-box-wrapper {
            background-color: #2d2d2d !important;
            border-color: #444 !important;
            color: #fff !important;
        }
        [data-theme="dark"] input::placeholder,
        [data-theme="dark"] textarea::placeholder { color: #888 !important; }

        /* 6. INTERACTIVE HOVER STATES */
        [data-theme="dark"] .dropdown-item:hover,
        [data-theme="dark"] .list-group-item:hover,
        [data-theme="dark"] .nav-btn:hover,
        [data-theme="dark"] .qa-item:hover,
        [data-theme="dark"] .search-item:hover,
        [data-theme="dark"] .sop-item:hover,
        [data-theme="dark"] .forum-item:hover,
        [data-theme="dark"] .learn-item:hover {
            background-color: #333 !important;
        }
        [data-theme="dark"] .dropdown-item { color: #e0e0e0; }

        /* 7. SPECIFIC FIXES */
        /* Modal Body Fix */
        [data-theme="dark"] .modal-body,
        [data-theme="dark"] .spotlight-results {
            background-color: #1e1e1e !important;
            color: #e0e0e0 !important;
        }

        /* Main Menu Links Fix */
        [data-theme="dark"] .menu-links a {
            color: #cccccc !important;
        }
        [data-theme="dark"] .menu-links a:hover {
            color: var(--fs-orange) !important;
        }

        /* Search Items Fix */
        [data-theme="dark"] .search-item,
        [data-theme="dark"] .search-item div {
            color: #e0e0e0 !important;
        }
        
        /* White Box Fixes from previous step */
        [data-theme="dark"] .hr-card-mini,
        [data-theme="dark"] .qa-item,
        [data-theme="dark"] .obj-card,
        [data-theme="dark"] .id-info-item,
        [data-theme="dark"] .video-wrapper,
        [data-theme="dark"] .bg-white.rounded {
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
            border: 1px solid #444 !important;
            box-shadow: none !important;
        }
        [data-theme="dark"] .obj-card h6,
        [data-theme="dark"] .qa-item span,
        [data-theme="dark"] .hr-card-mini span,
        [data-theme="dark"] .id-info-item span,
        [data-theme="dark"] .qa-item .text-dark,
        [data-theme="dark"] .hr-card-mini .text-dark {
            color: #fff !important;
        }
        [data-theme="dark"] .id-icon-box {
            background-color: #333 !important;
            color: var(--fs-orange) !important;
        }

        [data-theme="dark"] .msg-bot { background-color: #2d2d2d !important; color: #e0e0e0 !important; }
        [data-theme="dark"] .nav-btn { color: #e0e0e0; }
        /* Border fixes for list items/dividers */
        [data-theme="dark"] .border-bottom, 
        [data-theme="dark"] .border-top, 
        [data-theme="dark"] .border-end,
        [data-theme="dark"] .border { 
            border-color: #333 !important; 
        }
        [data-theme="dark"] .notif-item.unread { background-color: #2d2d2d !important; border-left-color: var(--fs-orange) !important; }
        /* ========================================= */

        body { font-family: 'Poppins', sans-serif; background-color: var(--fs-bg); color: var(--fs-text); font-size: 0.88rem; overflow-x: hidden; transition: background-color 0.3s; }
        a { text-decoration: none; color: inherit; transition: 0.2s; }

        /* --- DRAG & DROP STYLE --- */
        .sortable-ghost { opacity: 0.4; background-color: #f0f0f0; border: 2px dashed #ccc; }
        .sortable-drag { cursor: grabbing; }
        .card { cursor: grab; } /* Indikator bisa di drag */
        /* Kunci Layout: Jika ada class 'layout-locked' di body */
        body.layout-locked .card, 
        body.layout-locked .survey-card, 
        body.layout-locked .feedback-widget { cursor: default !important; }

        /* Utility overrides for class names 'text-orange' to use new brand color variable */
        .text-orange { color: var(--fs-orange) !important; }
        .bg-orange { background-color: var(--fs-orange) !important; }
        .bg-orange-subtle { background-color: var(--fs-orange-light) !important; }
        .border-orange-subtle { border-color: var(--fs-orange-light) !important; }

        /* --- NAVBAR & TICKER --- */
        .navbar { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(15px); border-bottom: 1px solid rgba(0,0,0,0.05); padding: 12px 0; z-index: 1030; transition: background-color 0.3s; }
        
        .nav-btn { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #666; background: transparent; border: 1px solid transparent; transition: var(--transition); cursor: pointer; font-size: 1rem; }
        @media (min-width: 768px) { .nav-btn { width: 42px; height: 42px; font-size: 1.1rem; } }
        .nav-btn:hover { background: var(--fs-orange-light); color: var(--fs-orange); transform: translateY(-2px); }
        
        .navbar-brand img { height: 32px; width: auto; }
        @media (min-width: 768px) { .navbar-brand img { height: 40px; } }

        .ticker-wrap { background: #ffffff; color: #333; height: 45px; overflow: hidden; white-space: nowrap; display: flex; align-items: center; border-bottom: 1px solid #eee; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }

        /* --- CARDS GENERAL --- */
        .card { border: none; border-radius: var(--card-radius); box-shadow: var(--shadow-subtle); background: #fff; margin-bottom: 24px; overflow: hidden; transition: var(--transition); }
        .card:hover { box-shadow: var(--shadow-hover); transform: translateY(-3px); }
        .card-header-clean { padding: 18px 24px; background: #fff; border-bottom: 1px solid #f0f0f0; font-weight: 600; color: var(--fs-orange); display: flex; align-items: center; gap: 10px; font-size: 0.95rem; }
        
        /* --- WIDGETS --- */
        .user-card { background: #fff; padding: 30px 20px; display: flex; flex-direction: column; align-items: center; text-align: center; gap: 5px; position: relative; }
        .user-avatar-xl { width: 110px; height: 110px; border-radius: 50%; object-fit: cover; border: 4px solid #fff; box-shadow: 0 10px 25px rgba(0, 169, 180, 0.25); margin-bottom: 5px; }
        
        .weather-card { background: linear-gradient(135deg, #ffffff 0%, #fff8f0 100%); border: 1px solid #fff; position: relative; }
        [data-theme="dark"] .weather-card { background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%); border-color: #444; }

        .qa-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; max-height: 320px; overflow-y: auto; padding: 4px; }
        .qa-item { display: flex; flex-direction: column; align-items: center; padding: 15px 10px; border-radius: 12px; background: #f8f9fa; transition: 0.2s; cursor: pointer; border: 1px solid transparent; color: inherit; text-decoration: none; }
        .qa-item:hover { background: #fff; border-color: var(--fs-orange); box-shadow: 0 4px 12px rgba(0,0,0,0.05); color: var(--fs-orange); }
        .qa-icon { width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 5px; }

        /* --- CENTER CONTENT --- */
        .hero-news { position: relative; height: 320px; border-radius: 0 0 16px 16px; overflow: hidden; }
        .hero-news img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .card:hover .hero-news img { transform: scale(1.05); }
        .hero-overlay { position: absolute; bottom: 0; width: 100%; background: linear-gradient(to top, rgba(0,0,0,0.9) 10%, transparent 100%); padding: 30px 25px; color: white; }
        .video-wrapper { background: #fff; border: 1px solid #f0f0f0; padding: 20px; border-radius: 16px; box-shadow: var(--shadow-subtle); transition: var(--transition); }
        .video-wrapper:hover { box-shadow: var(--shadow-hover); transform: translateY(-3px); }
        .hse-thumb { height: 100px; width: 100%; object-fit: cover; border-radius: 10px; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid #eee; }
        .hse-thumb:hover { transform: scale(1.03); box-shadow: 0 5px 15px rgba(0,0,0,0.1); opacity: 0.95; }
        .hse-text { font-size: 0.7rem; line-height: 1.3; height: 2.6em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; margin-top: 8px; color: #555; font-weight: 600; }

        /* --- FORUM & SOCIAL --- */
        .forum-item { transition: all 0.2s; border-left: 3px solid transparent; cursor: pointer; display: flex; gap: 15px; padding: 15px; border-bottom: 1px solid #f0f0f0; align-items: flex-start; }
        .forum-item:hover { background: #f8f9fa; border-left-color: var(--fs-orange); transform: translateX(5px); }
        .forum-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
        .tag-badge { font-size: 0.65rem; padding: 3px 8px; border-radius: 4px; font-weight: 600; color: white; margin-right: 8px; }
        .forum-stats { font-size: 0.75rem; color: #888; display: flex; gap: 12px; margin-top: 5px; }
        .ig-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; padding: 5px; }
        .ig-item { position: relative; padding-bottom: 100%; overflow: hidden; cursor: pointer; }
        .ig-item img { position: absolute; width: 100%; height: 100%; object-fit: cover; transition: 0.3s; }
        .ig-overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.5); display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; color: white; font-size: 0.8rem; gap: 5px; }
        .ig-item:hover .ig-overlay { opacity: 1; }
        .shorts-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; padding: 8px; }
        .shorts-item { position: relative; padding-bottom: 177%; border-radius: 12px; overflow: hidden; cursor: pointer; }
        .shorts-item img { position: absolute; width: 100%; height: 100%; object-fit: cover; transition: 0.3s; }
        .shorts-overlay { position: absolute; bottom: 0; width: 100%; background: linear-gradient(to top, rgba(0,0,0,0.8), transparent); padding: 10px; color: white; }
        .shorts-item:hover img { transform: scale(1.05); }
        .shorts-icon { position: absolute; top: 8px; right: 8px; font-size: 1.2rem; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }

        /* --- LEARNING & MISC --- */
        .hr-card-mini { padding: 15px; border-radius: 12px; background: #f9f9f9; text-align: center; border: 1px solid transparent; transition: 0.2s; display: flex; flex-direction: column; align-items: center; height: 100%; }
        .hr-card-mini:hover { border-color: var(--fs-orange); background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .hr-icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 8px; }
        .sop-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #f5f5f5; transition: 0.2s; cursor: pointer; }
        .sop-item:hover { background: #f9f9f9; border-left: 3px solid var(--fs-orange); }
        .sop-icon { width: 35px; height: 35px; background: #ffebee; color: #d32f2f; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .learn-item { display: flex; flex-direction: column; gap: 10px; padding: 20px; border-right: 1px solid #eee; height: 100%; transition: 0.2s; }
        .learn-item:hover { background: #f9f9f9; }
        .learn-header { display: flex; align-items: center; gap: 12px; margin-bottom: 5px; }
        .learn-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; }
        .progress { height: 6px; border-radius: 10px; background: #e0e0e0; width: 100%; }
        .progress-bar { background: var(--fs-orange); border-radius: 10px; }

        /* --- SURVEY & LEADERBOARD --- */
        .survey-card { background: linear-gradient(135deg, #00A9B4 0%, #008C95 100%); color: white; border-radius: 16px; padding: 20px; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between; cursor: grab; }
        .survey-content { z-index: 2; }
        .survey-qr { background: white; padding: 5px; border-radius: 8px; width: 80px; height: 80px; }
        .lb-item { display: flex; align-items: center; padding: 10px 15px; border-bottom: 1px solid #f5f5f5; gap: 10px; }
        .lb-rank { width: 25px; font-weight: bold; text-align: center; color: #999; }
        .lb-rank.top-1 { color: #FFD700; font-size: 1.2rem; }
        .lb-rank.top-2 { color: #C0C0C0; font-size: 1.1rem; }
        .lb-rank.top-3 { color: #CD7F32; font-size: 1.1rem; }
        .lb-img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 2px solid #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .lb-score { font-weight: 700; color: var(--fs-orange); font-size: 0.85rem; background: #fff8e1; padding: 2px 8px; border-radius: 10px; }
        .bday-item { display: flex; align-items: center; gap: 15px; padding: 12px 15px; border-bottom: 1px solid #f5f5f5; transition: 0.2s; }
        .bday-item:hover { background: #f9f9f9; }
        .bday-av { width: 42px; height: 42px; border-radius: 50%; background: #f1f1f1; color: #777; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; border: 2px solid #fff; }
        .bday-item.today .bday-av { background: var(--fs-orange); color: white; box-shadow: 0 0 0 3px rgba(255, 153, 0, 0.2); }

        /* --- FEEDBACK WIDGET --- */
        .feedback-widget { background: linear-gradient(135deg, #00A9B4 0%, #00d4e3 100%); border-radius: 16px; color: white; text-align: center; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 25px rgba(0, 169, 180, 0.3); padding: 25px 20px; }
        .feedback-widget:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0, 169, 180, 0.5); }
        @media (min-width: 992px) { 
            .feedback-widget { padding: 50px 30px; } 
            .feedback-widget h5 { font-size: 1.6rem; margin-bottom: 10px !important; }
            .feedback-widget p { font-size: 1rem !important; }
        }
        .star-rating { font-size: 2rem; color: #ddd; cursor: pointer; display: flex; justify-content: center; gap: 10px; }
        .star-rating i { transition: 0.2s; }
        .star-rating i.hovered, .star-rating i.selected { color: #FFD700; }

        /* --- SEARCH & MODAL COMPONENTS --- */
        .search-box-wrapper { position: relative; }
        .emp-trigger-card { cursor: pointer; transition: 0.2s; }
        .emp-trigger-card:hover { border-color: var(--fs-orange); }
        .emp-search-header { padding: 20px; border-bottom: 1px solid #eee; background: #fff; position: sticky; top: 0; z-index: 10; border-radius: 16px 16px 0 0; }
        .emp-item-lg { padding: 15px 20px; display: flex; align-items: center; gap: 15px; cursor: pointer; border-bottom: 1px solid #f9f9f9; transition: 0.2s; }
        .emp-item-lg:hover { background: #fff8e1; }
        .emp-item-lg img { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; }
        .spotlight-search .modal-content { border-radius: 16px; border: none; box-shadow: 0 15px 40px rgba(0,0,0,0.2); overflow: hidden; }
        .spotlight-input-group { padding: 8px; background: #fff; border-bottom: 1px solid #eee; }
        .spotlight-input { border: none; font-size: 1.2rem; padding: 15px; box-shadow: none !important; }
        .spotlight-results { max-height: 400px; overflow-y: auto; padding: 10px; background: #fafafa; }
        .search-item { display: flex; align-items: center; gap: 15px; padding: 12px 15px; border-radius: 8px; transition: 0.2s; cursor: pointer; color: #444; text-decoration: none; }
        .search-item:hover { background: #eef2f6; color: var(--fs-orange); }
        .search-item i { width: 25px; text-align: center; }

        /* --- NOTIFICATIONS --- */
        .notif-dropdown { 
            position: absolute; 
            top: 55px; 
            right: 0; 
            width: 360px; 
            background: white; 
            border-radius: 16px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.2); 
            z-index: 2000; 
            display: none; 
            overflow: hidden; 
            border: 1px solid #eee; 
            animation: fadeIn 0.2s ease-out; 
        }
        
        @media (max-width: 768px) {
            .notif-dropdown {
                position: fixed;
                top: 70px;
                left: 5%;
                right: 5%;
                width: 90%;
                max-width: 400px;
                transform: none;
            }
        }

        .notif-dropdown.show { display: block; }
        .notif-item { padding: 15px; border-bottom: 1px solid #f5f5f5; display: flex; align-items: flex-start; gap: 12px; transition: 0.2s; cursor: pointer; }
        .notif-item:hover { background-color: #f9f9f9; }
        .notif-item.unread { background-color: #fff3e0; border-left: 3px solid var(--fs-orange); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* --- CHATBOT --- */
        .chat-trigger { position: fixed; bottom: 30px; right: 30px; width: 65px; height: 65px; border-radius: 50%; background: var(--fs-orange); color: white; display: flex; align-items: center; justify-content: center; font-size: 28px; box-shadow: 0 10px 30px rgba(0, 169, 180, 0.4); z-index: 9999; cursor: pointer; transition: 0.3s; }
        .chat-trigger:hover { transform: scale(1.1); }
        .chat-window { position: fixed; bottom: 100px; right: 30px; width: 350px; height: 480px; background: white; border-radius: 20px; box-shadow: 0 25px 80px rgba(0,0,0,0.15); z-index: 9998; display: flex; flex-direction: column; overflow: hidden; opacity: 0; transform: scale(0); transform-origin: bottom right; transition: all 0.3s cubic-bezier(0.19, 1, 0.22, 1); }
        .chat-window.active { opacity: 1; transform: scale(1); }
        .msg-bubble { padding: 12px 16px; border-radius: 15px; font-size: 0.95rem; line-height: 1.5; max-width: 85%; position: relative; word-wrap: break-word;}
        .msg-user { background: linear-gradient(135deg, #00A9B4, #008C95); color: white; border-radius: 15px 15px 0 15px; margin-left: auto; box-shadow: 0 4px 10px rgba(0, 169, 180, 0.2); }
        .msg-bot { background: #f0f2f5; color: #333; border-radius: 15px 15px 15px 0; margin-right: auto; }
        .msg-bot h1, .msg-bot h2, .msg-bot h3 { font-size: 1rem !important; margin: 5px 0; font-weight: 700; color: #008C95; }
        .typing-indicator { display: flex; align-items: center; gap: 3px; padding: 5px 0; }
        .typing-dot { width: 6px; height: 6px; background: #999; border-radius: 50%; animation: bounce 1.4s infinite ease-in-out both; }
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        @keyframes bounce { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }

        /* --- MODAL: ANNIVERSARY & FOOTER --- */
        .modal-anniversary .modal-content { background: transparent; border: none; box-shadow: none; }
        .modal-anniversary .modal-body { padding: 0; position: relative; text-align: center; }
        .modal-anniversary img { max-width: 100%; border-radius: 16px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); animation: zoomIn 0.4s ease-out; }
        .btn-close-white-custom { position: absolute; top: -30px; right: 0; color: white; opacity: 0.8; cursor: pointer; font-size: 24px; }
        @keyframes zoomIn { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        footer { background: #1a1a1a; color: #999; padding: 80px 0; margin-top: 60px; font-size: 0.9rem; }
        .menu-category { display: flex; gap: 15px; margin-bottom: 20px; }
        .menu-cat-icon { width: 60px; height: 60px; border-radius: 12px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
        .ic-portal { background: #fce4ec; color: #d81b60; } .ic-info { background: #fff3e0; color: #fb8c00; } .ic-kpi { background: #e3f2fd; color: #1e88e5; } .ic-guide { background: #e0f2f1; color: #00897b; } .ic-hco { background: #f3e5f5; color: #8e24aa; }
        .menu-links { list-style: none; padding: 0; margin: 0; } .menu-links li { margin-bottom: 6px; } .menu-links a { text-decoration: none; color: #333; transition: 0.2s; } .menu-links a:hover { color: var(--fs-orange); padding-left: 5px; }
        .obj-card { padding: 30px 15px; border-radius: 16px; background: #fff; border: 1px solid #eee; transition: 0.3s; text-align: center; height: 100%; }
        .obj-card:hover { transform: translateY(-8px); border-color: var(--fs-orange); box-shadow: 0 15px 30px rgba(0,0,0,0.08); }
        .obj-icon { font-size: 3.5rem; color: var(--fs-orange); margin-bottom: 20px; display: block; transition: 0.3s; }
        .obj-val { font-weight: 700; font-size: 1rem; background: #fff3cd; padding: 6px 16px; border-radius: 30px; display: inline-block; margin-top: 10px; color: #856404; }
        @media (max-width: 767px) { .chat-window { width: 90%; right: 5%; bottom: 100px; height: 450px; } }

        /* --- ID CARD MODAL --- */
        .id-card-modal .modal-content { border-radius: 24px; border: none; overflow: hidden; background-color: #fff; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .id-card-header { background: linear-gradient(135deg, #00A9B4 0%, #005f6b 100%); height: 140px; position: relative; border-radius: 0 0 50% 50% / 20px; }
        .id-card-close { position: absolute; top: 15px; right: 15px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; transition: 0.2s; border: none; z-index: 20; }
        .id-card-close:hover { background: rgba(255,255,255,0.4); transform: rotate(90deg); }
        .id-card-avatar-container { margin-top: -75px; position: relative; display: flex; justify-content: center; margin-bottom: 20px; }
        .id-card-avatar { width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 6px solid #fff; box-shadow: 0 10px 25px rgba(0,0,0,0.15); background: #fff; }
        .id-card-body { padding: 0 30px 40px 30px; text-align: center; }
        .id-badge-role { display: inline-block; background: #E0F7FA; color: #008C95; padding: 6px 16px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; margin-bottom: 15px; letter-spacing: 0.5px; text-transform: uppercase; }
        .id-info-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-top: 25px; text-align: left; }
        .id-info-item { background: #F8F9FA; padding: 15px; border-radius: 12px; display: flex; align-items: center; gap: 15px; transition: 0.2s; border: 1px solid transparent; }
        .id-info-item:hover { background: #fff; border-color: #00A9B4; box-shadow: 0 5px 15px rgba(0, 169, 180, 0.1); }
        .id-icon-box { width: 40px; height: 40px; background: #fff; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #00A9B4; font-size: 1.1rem; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .btn-email-action { background: var(--fs-orange); color: white; border: none; border-radius: 12px; padding: 12px; width: 100%; font-weight: 600; margin-top: 20px; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-email-action:hover { background: #008C95; transform: translateY(-2px); color: white; }
        @media (min-width: 768px) { .id-info-grid { grid-template-columns: 1fr 1fr; } .item-full-width { grid-column: span 2; } }

        /* [BARU] BOOKING GRID STYLES */
        .booking-slot { 
            border: 1px solid #eee; padding: 8px; text-align: center; border-radius: 8px; cursor: pointer; transition: 0.2s; position: relative;
        }
        .booking-slot:hover { border-color: var(--fs-orange); background: #f0fdfe; }
        .booking-slot.booked { background-color: #ffebee; border-color: #ffcdd2; cursor: not-allowed; color: #c62828; }
        .booking-slot input[type="radio"] { display: none; }
        .booking-slot input[type="radio"]:checked + div { font-weight: bold; color: var(--fs-orange); }
        .booking-slot:has(input:checked) { border: 2px solid var(--fs-orange); background: #e0f7fa; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand navbar-light fixed-top bg-white shadow-sm">
        <div class="container-fluid px-3 px-md-4">
            
            <div class="d-flex align-items-center gap-2">
                <a class="navbar-brand me-0" href="#">
                    <img src="jpeg.png" alt="InsightSpace" onerror="this.src='https://placehold.co/120x35/00A9B4/white?text=InsightSpace';">
                </a>
                <button class="nav-btn" data-bs-toggle="modal" data-bs-target="#menuModal" title="Mega Menu">
                    <i class="fas fa-th-large"></i>
                </button>
            </div>

            <div class="d-flex align-items-center gap-1 gap-md-2 ms-auto">
                
                <button class="nav-btn d-none d-md-block" onclick="toggleTheme()" title="Toggle Theme">
                    <i class="fas fa-moon" id="themeIcon"></i>
                </button>

                <button class="nav-btn d-none d-md-block" onclick="toggleFullscreen()" title="Toggle Fullscreen" id="btnFullscreen">
                    <i class="fas fa-expand"></i>
                </button>
                
                <button class="nav-btn" data-bs-toggle="modal" data-bs-target="#searchModal" title="Search">
                    <i class="fas fa-search"></i>
                </button>

                <div class="vr mx-2 h-50 my-auto opacity-25 d-none d-md-block"></div>

                <div class="d-flex align-items-center gap-3 ps-0 ps-md-2">
                    <div class="text-end lh-1 d-none d-lg-block">
                        <small class="text-muted d-block" style="font-size: 0.7rem;">Welcome,</small>
                        <span class="fw-bold text-dark"><?php echo $user_name; ?></span>
                    </div>
                    
                    <div class="position-relative">
                        <button class="nav-btn" onclick="toggleNotif(event)" id="notifBtn">
                            <i class="fas fa-inbox"></i> 
                            <?php if($unread_count > 0): ?>
                                <span class="position-absolute top-0 end-0 p-1 bg-danger rounded-circle border border-white" style="transform: translate(-8px, 8px);"></span>
                            <?php endif; ?>
                        </button>
                        
                        <div class="notif-dropdown" id="notifDropdown">
                            <div class="p-3 border-bottom bg-white d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-dark">Inbox <span class="badge bg-danger rounded-pill ms-1"><?php echo $unread_count; ?></span></h6>
                                <a href="#" class="small text-decoration-none text-orange fw-bold">Mark all read</a>
                            </div>
                            <div class="notif-list" style="max-height: 350px; overflow-y: auto;">
                                <?php foreach($notifications as $n): ?>
                                <div class="notif-item <?php echo $n['unread'] ? 'unread' : ''; ?>">
                                    <div class="rounded-circle <?php echo $n['color']; ?> p-2 text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px; height:40px;"><i class="fas fa-envelope"></i></div>
                                    <div class="lh-sm w-100">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold text-dark fs-7"><?php echo $n['from']; ?></span>
                                            <small class="text-muted" style="font-size:0.65rem;"><?php echo $n['time']; ?></small>
                                        </div>
                                        <p class="mb-0 text-secondary small" style="line-height: 1.4;"><?php echo $n['msg']; ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php if(count($notifications) == 0): ?>
                                    <div class="p-4 text-center text-muted"><small>No new notifications</small></div>
                                <?php endif; ?>
                            </div>
                            <div class="p-2 bg-light text-center border-top">
                                <a href="#" class="small text-muted fw-bold">View All Notifications</a>
                            </div>
                        </div>
                    </div>

                    <a href="?action=logout" class="nav-btn text-danger d-flex align-items-center justify-content-center" title="Logout">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div style="height: 85px;"></div>
    <div class="ticker-wrap"><div class="container-fluid"><marquee behavior="scroll" direction="left" onmouseover="this.stop();" onmouseout="this.start();"><?php echo $ticker_html; ?></marquee></div></div>
    <div style="height: 30px;"></div>

    <div class="container-fluid px-4 pb-3">
        
        <?php if(isset($_SESSION['notif_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['notif_msg']; unset($_SESSION['notif_msg']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if(isset($_SESSION['notif_err'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $_SESSION['notif_err']; unset($_SESSION['notif_err']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4" id="dashboardGrid">
            
            <div class="col-12 col-lg-3 sortable-col" id="col_left">
                
                <div class="card user-card" id="card_user_profile">
                    <div class="position-absolute top-0 end-0 m-3 dropdown">
                        <button class="btn btn-sm btn-light rounded-circle shadow-sm text-muted border" data-bs-toggle="dropdown" aria-expanded="false" style="width: 32px; height: 32px;">
                            <i class="fas fa-cog"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-2" style="min-width: 220px;">
                            <li><h6 class="dropdown-header text-uppercase small fw-bold">Settings</h6></li>
                            <li>
                                <button class="dropdown-item rounded-2 small py-2 d-flex align-items-center" onclick="toggleLayoutLock()" id="btnLockLayout">
                                    <i class="fas fa-lock-open me-2 text-secondary" style="width: 16px;"></i> <span>Lock Layout</span>
                                </button>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button class="dropdown-item rounded-2 small py-2 text-danger d-flex align-items-center" onclick="showResetModal()">
                                    <i class="fas fa-undo me-2" style="width: 16px;"></i> Reset Layout
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="position-relative d-inline-block">
                        <img src="<?php echo $user_photo; ?>" class="user-avatar-xl" alt="User">
                        <span class="position-absolute bottom-0 end-0 p-2 bg-success border border-light rounded-circle" style="margin-bottom: 5px;"></span>
                    </div>
                    <h5 class="text-orange fw-bold mt-3 mb-1">Hello, <?php echo $user_name; ?></h5>
                    <div class="d-flex align-items-center gap-2 justify-content-center mb-3">
                        <span class="badge bg-orange-subtle text-orange border border-orange-subtle px-3 rounded-pill">
                            <i class="fas fa-building me-1"></i> <?php echo $user_dept; ?>
                        </span>
                    </div>
                    
                    <div class="d-flex gap-2 justify-content-center mb-3 w-100 px-3">
                        <span class="badge bg-light text-muted border flex-fill"><i class="fas fa-fire text-danger"></i> <?php echo $login_streak; ?> Day Streak</span>
                        <span class="badge bg-warning-subtle text-dark border border-warning-subtle flex-fill cursor-pointer" data-bs-toggle="modal" data-bs-target="#redeemModal">
                            <i class="fas fa-coins text-warning"></i> 2,450 FS Coins
                        </span>
                    </div>
                    <button class="btn btn-sm btn-outline-warning w-100 mb-3 fw-bold text-dark" data-bs-toggle="modal" data-bs-target="#redeemModal" style="background-color: #fff8e1; border-color: #ffecb3;">
                        <i class="fas fa-gift me-1"></i> Redeem Reward
                    </button>
                    <div class="d-flex gap-1 justify-content-center mb-3 flex-wrap">
                        <?php foreach($user_badges as $bg): ?>
                        <div class="badge <?php echo $bg['bg']; ?> text-dark" title="<?php echo $bg['title']; ?>"><i class="<?php echo $bg['icon']; ?> <?php echo $bg['color']; ?>"></i></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="bg-light rounded p-2 w-100 border border-dashed">
                        <small class="text-muted d-block fw-bold" style="font-size:0.65rem; letter-spacing: 1px;">EMPLOYEE ID</small>
                        <span class="fw-bold text-dark font-monospace"><?php echo $user_nik; ?></span>
                    </div>
                    
                    <button class="btn btn-outline-secondary btn-sm w-100 mt-3 rounded-pill" data-bs-toggle="modal" data-bs-target="#myQrModal">
                        <i class="fas fa-qrcode me-1"></i> Share Contact
                    </button>

                    <div class="mt-3 text-muted small fst-italic lh-sm">"Inspirasi hari ini adalah solusi hari esok."</div>
                </div>

                <div class="card bg-orange text-white" id="card_quick_booking" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#bookingModal">
                    <div class="card-body d-flex align-items-center justify-content-between">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="far fa-calendar-plus me-2"></i>Book Room</h5>
                            <small class="opacity-75">Quick reservation for today</small>
                        </div>
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>

                <div class="card" id="card_mood_meter">
                    <div class="card-header-clean"><i class="fas fa-smile-beam text-warning"></i> Daily Check-in</div>
                    <div class="card-body p-3 text-center">
                        <p class="small text-muted mb-3">How are you feeling today?</p>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="submit_mood">
                            <div class="d-flex justify-content-center gap-3 mb-2">
                                <button type="submit" name="mood" value="Happy" class="btn btn-light border fs-4 rounded-circle p-2" title="Happy">😄</button>
                                <button type="submit" name="mood" value="Neutral" class="btn btn-light border fs-4 rounded-circle p-2" title="Okay">😐</button>
                                <button type="submit" name="mood" value="Sad" class="btn btn-light border fs-4 rounded-circle p-2" title="Tired">😓</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card weather-card p-4" id="card_weather">
                <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold text-orange mb-0">Jakarta</h6>
                            <div id="liveClock" class="fw-bold text-dark small my-1" style="font-family: monospace; font-size: 0.9rem;">--:--:-- WIB</div>
                            <small class="text-muted">Cerah Berawan</small>
                        </div>
                        <i class="fas fa-cloud-sun fa-3x text-warning opacity-75"></i>
                    </div>
                    <div class="display-5 fw-bold mt-3 text-dark">30°C</div>
                </div>
                
                <div class="card" id="card_quick_apps"><div class="card-header-clean"><i class="fas fa-bolt"></i> Quick Apps</div><div class="card-body p-3"><div class="qa-grid"><?php foreach($quick_apps as $app): ?><a href="<?php echo $app['link']; ?>" class="qa-item text-decoration-none text-dark" target="_blank"><div class="qa-icon <?php echo $app['bg'] . ' ' . $app['text']; ?>"><?php if(isset($app['is_img']) && $app['is_img']): ?><img src="<?php echo $app['icon']; ?>" style="width:24px;height:24px;"><?php else: ?><i class="<?php echo $app['icon']; ?>"></i><?php endif; ?></div><span class="fw-bold small text-truncate w-100 text-center text-dark"><?php echo $app['name']; ?></span></a><?php endforeach; ?></div></div></div>
                
                <div class="card" id="card_upcoming_events">
                    <div class="card-header-clean"><i class="far fa-calendar-alt"></i> Upcoming Events</div>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item border-0 py-3 d-flex gap-3 align-items-center"><div class="text-center bg-warning bg-opacity-10 text-warning rounded p-2" style="width: 50px;"><small class="d-block fw-bold" style="font-size:0.6rem;">OCT</small><span class="h5 fw-bold m-0">30</span></div><div class="lh-sm"><small class="fw-bold">Anniversary Celebration</small></div></div>
                        <div class="list-group-item border-0 py-3 d-flex gap-3 align-items-center"><div class="text-center bg-danger bg-opacity-10 text-danger rounded p-2" style="width: 50px;"><small class="d-block fw-bold" style="font-size:0.6rem;">NOV</small><span class="h5 fw-bold m-0">24</span></div><div class="lh-sm"><small class="fw-bold">Townhall Q4 2025</small></div></div>
                    </div>
                </div>

                <div class="card" id="card_instagram"><div class="card-header-clean"><i class="fab fa-instagram text-danger"></i> Instagram</div><div class="ig-grid"><?php foreach($ig_feed as $ig): ?><div class="ig-item"><img src="<?php echo $ig['img']; ?>"><div class="ig-overlay"><span><i class="fas fa-heart"></i> <?php echo $ig['likes']; ?></span></div></div><?php endforeach; ?></div></div>
                <div class="card" id="card_social_highlights"><div class="card-header-clean text-dark"><i class="fas fa-play-circle text-danger"></i> Social Highlights</div><div class="shorts-grid"><?php foreach($social_shorts as $ss): ?><div class="shorts-item"><img src="<?php echo $ss['img']; ?>"><div class="shorts-overlay"><span class="badge <?php echo $ss['bg']; ?> bg-opacity-75"><i class="fas fa-eye"></i> <?php echo $ss['views']; ?></span></div><div class="shorts-icon <?php echo $ss['col']; ?>"><i class="<?php echo $ss['icon']; ?>"></i></div></div><?php endforeach; ?></div></div>

                <div class="feedback-widget mb-4" id="card_feedback_widget" data-bs-toggle="modal" data-bs-target="#feedbackModal">
                   <h5 class="fw-bold mb-1"><i class="far fa-star me-2"></i> Rate Our Portal</h5>
                   <p class="small mb-0 text-white-50">Help us improve your experience!</p>
                </div>
            </div>

            <div class="col-12 col-lg-6 sortable-col" id="col_center">
                 <div class="card p-0 border-0" id="card_hero_news"><div class="hero-news"><img src="<?php echo $news_list[0]['img']; ?>" alt="News"><div class="hero-overlay"><span class="badge bg-orange mb-2">HIGHLIGHT</span><h3 class="text-white fw-bold mb-1"><?php echo $news_list[0]['title']; ?></h3><p class="text-white-50 mb-0 small">Ruang Inspirasi, Kolaborasi, dan Solusi.</p></div></div></div>
                 
                 <div class="card" id="card_recent_updates"><div class="card-header-clean"><i class="far fa-newspaper"></i> Recent Updates</div><div class="card-body p-3"><div class="row g-3"><?php foreach(array_slice($news_list, 1, 4) as $news): ?><div class="col-6 col-md-3 text-center"><div class="ratio ratio-4x3 bg-light mb-2 rounded overflow-hidden"><img src="<?php echo $news['img']; ?>" class="object-fit-cover"></div><small class="d-block fw-bold text-truncate text-dark"><?php echo $news['title']; ?></small><small class="text-muted" style="font-size:0.65rem;"><?php echo $news['cat']; ?></small></div><?php endforeach; ?></div></div></div>
                 
                 <div class="card" id="card_hr_hub"><div class="card-header-clean"><i class="fas fa-briefcase text-primary"></i> HR & Governance Hub</div><div class="card-body p-4"><div class="row g-3 mb-4"><?php foreach($hr_stats as $hr): ?><div class="col-6 col-md-3"><div class="hr-card-mini"><div class="hr-icon <?php echo $hr['bg'] . ' ' . $hr['col']; ?>"><i class="<?php echo $hr['icon']; ?>"></i></div><span class="fw-bold d-block text-dark"><?php echo $hr['val']; ?></span><small class="text-muted" style="font-size:0.7rem;"><?php echo $hr['label']; ?></small></div></div><?php endforeach; ?></div></div></div>
                 
                 <div class="card" id="card_hse_center">
                     <div class="card-header-clean"><i class="fas fa-hard-hat text-success"></i> HSE Center</div>
                     <div class="card-body">
                         <div class="row g-3 text-center align-items-start"> 
                             <?php foreach($hse_updates as $hse): ?>
                             <div class="col-4">
                                 <img src="<?php echo $hse['img']; ?>" class="hse-thumb shadow-sm" onclick="showHseImage('<?php echo $hse['full']; ?>', '<?php echo $hse['title']; ?>')">
                                 <div class="hse-text"><?php echo $hse['title']; ?></div>
                             </div>
                             <?php endforeach; ?>
                         </div>
                     </div>
                 </div>

                 <div class="card" id="card_forum">
                    <div class="card-header-clean d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-comments text-info"></i> Employee Discussion</span>
                        <button class="btn btn-sm btn-light text-muted fw-bold border" data-bs-toggle="modal" data-bs-target="#postForumModal"><i class="fas fa-plus me-1"></i> New Topic</button>
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                        <?php foreach($forum_topics as $ft): ?>
                        <div class="forum-item p-3" onclick="openForumModal(<?php echo htmlspecialchars(json_encode($ft)); ?>)">
                            <div class="d-flex gap-3">
                                <img src="<?php echo $ft['av']; ?>" class="forum-avatar shadow-sm">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="fw-bold text-dark mb-1" style="font-size: 0.9rem;"><?php echo $ft['t']; ?></h6>
                                        <small class="text-muted" style="font-size: 0.7rem; white-space: nowrap;"><?php echo $ft['tm']; ?></small>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="badge <?php echo $ft['c_bg']; ?> tag-badge"><?php echo $ft['cat']; ?></span>
                                        <small class="text-muted" style="font-size: 0.75rem;">by <?php echo $ft['u']; ?></small>
                                    </div>
                                    <div class="forum-stats">
                                        <span><i class="far fa-thumbs-up"></i> <?php echo $ft['l']; ?></span>
                                        <span><i class="far fa-comment-alt"></i> <?php echo $ft['c']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                 </div>

                 <div class="card" id="card_sop"><div class="card-header-clean text-dark"><i class="fas fa-file-alt text-primary"></i> Latest SOP & Guidelines</div><div class="list-group list-group-flush"><?php foreach($sop_list as $sop): ?><div class="sop-item"><div class="d-flex align-items-center gap-3"><div class="sop-icon"><i class="fas fa-file-<?php echo strtolower($sop['type']) == 'pdf' ? 'pdf' : 'word'; ?>"></i></div><div class="lh-1"><small class="d-block fw-bold text-dark"><?php echo $sop['title']; ?></small><small class="text-muted" style="font-size:0.65rem;"><?php echo $sop['date']; ?></small></div></div><span class="badge bg-light text-dark border"><?php echo $sop['type']; ?></span></div><?php endforeach; ?></div></div>
                 
                 <div class="video-wrapper mb-4" id="card_video_gallery"><div class="d-flex justify-content-between mb-3"><h6 class="fw-bold text-orange m-0"><i class="fab fa-youtube me-2"></i>Video Gallery</h6></div><div class="ratio ratio-16x9 rounded overflow-hidden shadow-sm mb-3"><iframe src="https://www.youtube.com/embed/hKoyVAosvQ4" allowfullscreen></iframe></div></div>
            </div>

            <div class="col-12 col-lg-3 sortable-col" id="col_right">
                
                <div class="card" id="card_my_schedule">
                    <div class="card-header-clean"><i class="fas fa-clock text-success"></i> My Bookings Today</div>
                    <div class="card-body p-0">
                        <?php 
                        $has_booking = false;
                        if(isset($bookings_db[$today_date])) {
                            foreach($bookings_db[$today_date] as $r => $slots) {
                                foreach($slots as $t => $u) {
                                    if($u === $_SESSION['user_name']) {
                                        $has_booking = true;
                                        echo "<div class='p-3 border-bottom'><div class='fw-bold text-orange'>$t</div><small>Room: {$meeting_rooms[$r]['name']}</small></div>";
                                    }
                                }
                            }
                        }
                        if(!$has_booking) echo "<div class='p-4 text-center text-muted small'>No meetings scheduled today.</div>";
                        ?>
                    </div>
                </div>

                <div class="card" id="card_todo_list">
                    <div class="card-header-clean d-flex justify-content-between">
                        <span><i class="fas fa-check-square text-success"></i> My Tasks</span>
                        <small class="text-muted" style="font-size:0.7rem;">Local</small>
                    </div>
                    <div class="card-body p-3">
                        <div class="input-group mb-3">
                            <input type="text" id="todoInput" class="form-control form-control-sm" placeholder="Add task...">
                            <button class="btn btn-outline-secondary btn-sm" onclick="addTodo()"><i class="fas fa-plus"></i></button>
                        </div>
                        <ul id="todoList" class="list-group list-group-flush small">
                            </ul>
                    </div>
                </div>

                <div class="card" id="card_coverage">
                    <div class="card-header-clean text-dark">
                        <i class="fas fa-map-marked-alt text-success"></i> Coverage & Services
                    </div>
                    <div class="p-0">
                        <div class="position-relative bg-light text-center" style="height: 140px; overflow:hidden;">
                            <img src="https://images.unsplash.com/photo-1569336415962-a4bd9f69cd83?w=600" style="width:100%; height:100%; object-fit:cover; opacity:0.6;">
                            <div class="position-absolute top-50 start-50 translate-middle">
                            <button class="btn btn-sm btn-primary rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#coverageMapModal">
                                <i class="fas fa-search-location me-1"></i> Cek Area Saya
                            </button>
                        </div>
                        </div>
                        <div class="list-group list-group-flush small">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-home text-orange me-2"></i>FiberStar Home</span>
                                <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-building text-primary me-2"></i>FiberStar Business</span>
                                <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-network-wired text-info me-2"></i>Managed Service</span>
                                <i class="fas fa-chevron-right text-muted" style="font-size:0.7rem"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card" id="card_dictionary">
                    <div class="card-header-clean text-dark">
                        <i class="fas fa-book text-danger"></i> Fiber & Worklife Kamus
                    </div>
                    <div class="card-body p-3">
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" id="dictSearch" class="form-control border-start-0 bg-light" placeholder="Cari istilah (cth: OLT, Cuti)..." onkeyup="filterDictionary()">
                        </div>
                        
                        <div class="accordion accordion-flush" id="dictAccordion" style="max-height: 250px; overflow-y: auto;">
                            <div class="accordion-item dict-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 small fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#term1">
                                        <span class="text-primary me-2">TECH</span> OLT (Optical Line Terminal)
                                    </button>
                                </h2>
                                <div id="term1" class="accordion-collapse collapse" data-bs-parent="#dictAccordion">
                                    <div class="accordion-body small text-muted bg-light p-2">
                                        Perangkat titik akhir layanan jaringan optik pasif yang terletak di sentral penyedia layanan (ISP/FiberStar).
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item dict-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 small fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#term2">
                                        <span class="text-primary me-2">TECH</span> ONT (Optical Network Terminal)
                                    </button>
                                </h2>
                                <div id="term2" class="accordion-collapse collapse" data-bs-parent="#dictAccordion">
                                    <div class="accordion-body small text-muted bg-light p-2">
                                        Modem optik yang berada di sisi pelanggan (rumah/kantor) untuk mengubah sinyal cahaya menjadi sinyal listrik.
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item dict-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 small fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#term3">
                                        <span class="text-success me-2">HR</span> Cuti Besar (Sabbatical)
                                    </button>
                                </h2>
                                <div id="term3" class="accordion-collapse collapse" data-bs-parent="#dictAccordion">
                                    <div class="accordion-body small text-muted bg-light p-2">
                                        Hak istirahat panjang yang diberikan kepada karyawan setelah masa kerja 6 tahun berturut-turut.
                                    </div>
                                </div>
                            </div>

                             <div class="accordion-item dict-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed py-2 small fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#term4">
                                        <span class="text-success me-2">HR</span> WFA (Work From Anywhere)
                                    </button>
                                </h2>
                                <div id="term4" class="accordion-collapse collapse" data-bs-parent="#dictAccordion">
                                    <div class="accordion-body small text-muted bg-light p-2">
                                        Kebijakan fleksibilitas kerja FiberStar yang memungkinkan karyawan bekerja dari lokasi mana saja selama terkoneksi internet.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card p-3 text-center" id="card_subscriber_chart"><h6 class="fw-bold text-primary mb-3">SUBSCRIBER</h6><h2 class="fw-bold mb-3 text-dark">439.959</h2><div style="height: 100px;"><canvas id="milestoneChart"></canvas></div><div class="mt-3 small text-muted"><i class="fas fa-circle text-warning me-1"></i>Target <i class="fas fa-circle text-orange ms-2 me-1"></i>Actual</div></div>
                
                <div class="survey-card mb-4 shadow" id="card_survey">
                    <div class="survey-content">
                        <h5 class="fw-bold mb-1"><i class="fas fa-poll-h me-2"></i> <?php echo $active_quiz['title']; ?></h5>
                        <p class="mb-2 text-white-50 small"><?php echo $active_quiz['desc']; ?></p>
                        <div class="badge bg-white text-orange rounded-pill px-3 py-2"><i class="fas fa-gift me-1"></i> Reward: <?php echo $active_quiz['points']; ?></div>
                    </div>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($active_quiz['link']); ?>" class="survey-qr" alt="Scan QR">
                </div>

                <div class="card" id="card_leaderboard"><div class="card-header-clean text-dark"><i class="fas fa-trophy text-warning"></i> Leaderboard</div><div class="card-body p-0"><?php foreach($leaderboard as $l): $rc = $l['rank']==1?'top-1':($l['rank']==2?'top-2':($l['rank']==3?'top-3':'')); ?><div class="lb-item"><div class="lb-rank <?php echo $rc; ?>"><?php echo ($l['rank']<=3?'<i class="fas fa-medal"></i>':$l['rank']); ?></div><img src="<?php echo $l['img']; ?>" class="lb-img"><div class="lh-1 flex-grow-1"><small class="d-block fw-bold text-dark"><?php echo $l['name']; ?></small><small class="text-muted" style="font-size:0.65rem;"><?php echo $l['dept']; ?></small></div><div class="lb-score"><?php echo $l['score']; ?></div></div><?php endforeach; ?></div></div>
                
                <div class="card emp-trigger-card" id="card_search_employee" data-bs-toggle="modal" data-bs-target="#employeeSearchModal"><div class="card-header-clean text-dark"><i class="fas fa-users-viewfinder text-orange"></i> Find Crew</div><div class="card-body p-3 pt-0"><div class="search-box-wrapper mt-2 bg-light rounded p-3 border text-muted d-flex align-items-center cursor-pointer"><i class="fas fa-search me-2"></i> Click to search...</div></div></div>
                
                <div class="card" id="card_birthdays"><div class="card-header-clean text-danger"><i class="fas fa-birthday-cake"></i> Birthdays</div><div class="list-group list-group-flush"><?php foreach($birthdays as $b): $ini = substr($b['name'],0,1); ?><div class="list-group-item bday-item <?php echo $b['is_today']?'today':''; ?>"><div class="bday-av"><?php echo $ini; ?></div><div class="lh-1 flex-grow-1"><small class="d-block fw-bold text-dark"><?php echo $b['name']; ?></small><small class="text-muted" style="font-size:0.7rem;"><?php echo $b['role']; ?></small></div><div class="text-end"><?php if($b['is_today']): ?><i class="fas fa-gift text-warning animate-bounce"></i><?php else: ?><small class="text-muted fw-bold" style="font-size:0.7rem;"><?php echo $b['date']; ?></small><?php endif; ?></div></div><?php endforeach; ?></div></div>
                
                <div class="card p-3 position-relative overflow-hidden" id="card_new_joiner"><i class="fas fa-user-plus fa-4x text-warning position-absolute top-0 end-0 opacity-25 p-2"></i><h6 class="fw-bold text-warning mb-3">New Joiner</h6><?php foreach($new_joiners as $nj): ?><div class="d-flex align-items-center gap-3 mb-2"><div class="bg-light rounded-circle d-flex align-items-center justify-content-center" style="width:45px; height:45px; border:1px solid #eee;"><span class="fw-bold text-muted"><?php echo $nj['initials']; ?></span></div><div class="lh-1"><span class="fw-bold d-block fs-7"><?php echo $nj['name']; ?></span><small class="text-muted" style="font-size:0.7rem;"><?php echo $nj['pos']; ?></small></div></div><div class="text-end mt-1"><small class="text-muted" style="font-size:0.7rem;">Joined <?php echo $nj['date']; ?></small></div><?php endforeach; ?></div>
                <div class="card" id="card_new_born"><div class="card-header-clean text-info"><i class="fas fa-baby"></i> New Born</div><div class="list-group list-group-flush"><?php foreach($new_borns as $nb): ?><div class="list-group-item border-0 py-3 d-flex gap-3 align-items-center"><div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width:40px; height:40px;"><i class="fas fa-baby-carriage"></i></div><div class="lh-1"><small class="d-block fw-bold text-dark"><?php echo $nb['name']; ?></small><small class="text-muted" style="font-size:0.7rem;"><?php echo $nb['baby']; ?> (<?php echo $nb['date']; ?>)</small></div></div><?php endforeach; ?></div></div>
                
                <div class="card" id="card_job_vacancy">
                    <div class="card-header-clean text-primary"><i class="fas fa-briefcase"></i> Internal Job Vacancy</div>
                    <div class="list-group list-group-flush">
                        <?php foreach($job_vacancies as $job): ?>
                        <div class="list-group-item border-0 py-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-bold text-dark"><?php echo $job['title']; ?></span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?php echo $job['type']; ?></span>
                            </div>
                            <div class="d-flex gap-3 small text-muted">
                                <span><i class="fas fa-map-marker-alt me-1"></i> <?php echo $job['loc']; ?></span>
                                <span><i class="fas fa-clock me-1"></i> <?php echo $job['exp']; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <a href="#" class="p-2 text-center small fw-bold text-decoration-none text-muted bg-light d-block">See All Positions</a>
                    </div>
                </div>
                <div class="card" id="card_newsletter"><div class="card-header-clean text-warning"><i class="far fa-envelope-open"></i> Newsletter</div><div class="card-body p-0"><div class="list-group list-group-flush"><?php foreach($newsletter_list as $nl): ?><div class="p-3 d-flex gap-3 align-items-center bg-light bg-opacity-25 cursor-pointer border-bottom"><i class="fas fa-file-pdf text-danger fs-3"></i><div class="lh-1"><small class="fw-bold d-block text-dark"><?php echo $nl['vol']; ?> - <?php echo $nl['month']; ?></small><small class="text-muted" style="font-size:0.7rem;">Download <?php echo $nl['file']; ?></small></div></div><?php endforeach; ?></div></div></div>
            </div>
        </div>
        
        <div class="row mt-2">
            <div class="col-12">
                <div class="card" id="card_learning_hub">
                    <div class="card-header-clean text-primary"><i class="fas fa-graduation-cap"></i> Learning Hub</div>
                    <div class="card-body p-0">
                        <div class="row g-0">
                            <?php foreach($learning_hub as $i => $learn): ?>
                            <div class="col-12 col-md-6 col-lg-3 border-end border-bottom">
                                <div class="learn-item">
                                    <div class="learn-header">
                                        <div class="learn-icon <?php echo $learn['col']; ?>"><i class="<?php echo $learn['icon']; ?>"></i></div>
                                        <div class="lh-sm">
                                            <small class="fw-bold d-block text-dark"><?php echo $learn['course']; ?></small>
                                            <small class="text-muted">On Progress</small>
                                        </div>
                                    </div>
                                    <div class="progress mt-2"><div class="progress-bar" style="width: <?php echo $learn['progress']; ?>%"></div></div>
                                    <div class="d-flex justify-content-between mt-1"><small class="text-muted fs-7">Complete</small><small class="fw-bold text-primary fs-7"><?php echo $learn['progress']; ?>%</small></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="bg-white py-4 border-top mt-0">
        <div class="container">
            <div class="text-center mb-4"><h4 class="fw-bold ls-1 text-dark">INSIGHTSPACE OBJECTIVES 2025</h4><div class="bg-orange mx-auto rounded" style="width: 70px; height: 5px;"></div></div>
            <div class="row g-4 justify-content-center">
                <div class="col-12 col-sm-6 col-md-3"><div class="obj-card"><i class="fas fa-chart-line obj-icon"></i><h6 class="fw-bold text-dark">EBITDA</h6><div class="obj-val">68%</div></div></div>
                <div class="col-12 col-sm-6 col-md-3"><div class="obj-card"><i class="fas fa-wallet obj-icon"></i><h6 class="fw-bold text-dark">REVENUE</h6><div class="obj-val">Rp 2.2 T</div></div></div>
                <div class="col-12 col-sm-6 col-md-3"><div class="obj-card"><i class="fas fa-network-wired obj-icon"></i><h6 class="fw-bold text-dark">BROADBAND</h6><div class="obj-val">15.6%</div></div></div>
                <div class="col-12 col-sm-6 col-md-3"><div class="obj-card"><i class="fas fa-award obj-icon"></i><h6 class="fw-bold text-dark">PERFORMANCE</h6><div class="obj-val">100%</div></div></div>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <div class="row align-items-center gy-4">
                <div class="col-12 col-md-3 text-center text-md-start">
                    <div class="bg-white rounded p-2 d-inline-block">
                        <img src="jpeg.png" height="35" alt="InsightSpace">
                    </div>
                    <small class="d-block mt-2 opacity-50">Ruang Inspirasi, Kolaborasi, dan Solusi.</small>
                </div>
                
                <div class="col-12 col-md-6 text-center text-md-start">
                    <p class="mb-0 text-white fw-bold">Insight Lab Tim</p>
                    <p class="mb-0 opacity-50">Jakarta Selatan - Indonesia</p>
                </div>
                
                <div class="col-12 col-md-3 text-center text-md-end">
                    <div class="d-flex justify-content-center justify-content-md-end gap-3">
                        <a href="#" class="text-white"><i class="fab fa-linkedin fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-facebook fa-lg"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <div class="modal fade" id="redeemModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="fw-bold mb-0"><i class="fas fa-store text-warning me-2"></i>FS Coin Store</h5>
                        <small class="text-muted">Saldo Anda: <span class="text-orange fw-bold">2,450 Coins</span></small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="card h-100 border shadow-sm">
                                <div class="p-3 text-center bg-light rounded-top">
                                    <i class="fas fa-tshirt fa-3x text-primary mb-2"></i>
                                    <h6 class="fw-bold mb-0 text-dark">Kaos Exclusive</h6>
                                </div>
                                <div class="card-body p-2 text-center">
                                    <small class="d-block text-muted mb-2">Limited Edition 2025</small>
                                    <button class="btn btn-sm btn-outline-warning w-100 fw-bold rounded-pill">500 Coins</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card h-100 border shadow-sm">
                                <div class="p-3 text-center bg-light rounded-top">
                                    <i class="fas fa-ticket-alt fa-3x text-danger mb-2"></i>
                                    <h6 class="fw-bold mb-0 text-dark">Voucher MAP</h6>
                                </div>
                                <div class="card-body p-2 text-center">
                                    <small class="d-block text-muted mb-2">Rp 100.000 Value</small>
                                    <button class="btn btn-sm btn-outline-warning w-100 fw-bold rounded-pill">1200 Coins</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card h-100 border shadow-sm">
                                <div class="p-3 text-center bg-light rounded-top">
                                    <i class="fas fa-mug-hot fa-3x text-success mb-2"></i>
                                    <h6 class="fw-bold mb-0 text-dark">Tumbler Corkcicle</h6>
                                </div>
                                <div class="card-body p-2 text-center">
                                    <small class="d-block text-muted mb-2">Custom Name</small>
                                    <button class="btn btn-sm btn-secondary w-100 fw-bold rounded-pill" disabled>Sold Out</button>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="card h-100 border shadow-sm">
                                <div class="p-3 text-center bg-light rounded-top">
                                    <i class="fas fa-clock fa-3x text-info mb-2"></i>
                                    <h6 class="fw-bold mb-0 text-dark">Pulang Cepat</h6>
                                </div>
                                <div class="card-body p-2 text-center">
                                    <small class="d-block text-muted mb-2">Voucher 1 Jam</small>
                                    <button class="btn btn-sm btn-outline-warning w-100 fw-bold rounded-pill">300 Coins</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="myQrModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-body text-center p-4">
                    <h5 class="fw-bold mb-3 text-dark">Share My Contact</h5>
                    <div class="bg-white p-3 rounded shadow-sm border mb-3 d-inline-block">
                         <img src="<?php echo $my_qr_url; ?>" alt="My QR" class="img-fluid rounded">
                    </div>
                    <p class="small text-muted">Scan this QR code to save my contact directly to your phone.</p>
                    <button type="button" class="btn btn-light btn-sm rounded-pill w-100" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="resetLayoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content rounded-4 border-0 p-3">
                <div class="modal-body text-center">
                    <div class="mb-3">
                        <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 65px; height: 65px;">
                            <i class="fas fa-sync-alt fa-2x"></i>
                        </div>
                    </div>
                    <h5 class="fw-bold mb-2 text-dark">Reset Layout?</h5>
                    <p class="text-muted small mb-4">Tampilan dashboard akan kembali ke pengaturan awal. Susunan widget Anda akan dihapus.</p>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light w-100 rounded-pill fw-bold" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-warning text-white w-100 rounded-pill fw-bold" onclick="confirmReset()">Reset</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade emp-search-modal" id="employeeSearchModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"> 
            <div class="modal-content" style="border-radius: 16px; overflow: hidden;">
                <div class="emp-search-header">
                    <div class="d-flex justify-content-between align-items-center mb-3"><h5 class="fw-bold m-0 text-dark"><i class="fas fa-users text-orange me-2"></i> Find Colleague</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="input-group shadow-sm"><span class="input-group-text bg-white border-0 ps-3"><i class="fas fa-search text-muted"></i></span><input type="text" id="modalEmpSearchInput" class="form-control border-0" placeholder="Type name, department, or position..." autocomplete="off" style="font-size: 1.1rem; padding: 15px;"></div>
                </div>
                <div class="modal-body bg-light" id="modalEmpSearchResults" style="min-height: 300px;">
                    <div class="text-center py-5 text-muted"><i class="fas fa-user-friends fa-3x mb-3 opacity-25"></i><p>Start typing to find your colleague...</p></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade id-card-modal" id="employeeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="id-card-header">
                    <button type="button" class="id-card-close" data-bs-dismiss="modal"><i class="fas fa-times"></i></button>
                    <div class="text-center pt-4 text-white opacity-75">
                        <div class="bg-white rounded p-1 d-inline-block"><img src="jpeg.png" height="25"></div>
                    </div>
                </div>
                <div class="id-card-avatar-container"><img src="" id="modalEmpImg" class="id-card-avatar"></div>
                <div class="id-card-body">
                    <h4 class="fw-bold text-dark mb-1" id="modalEmpName"></h4>
                    <p class="text-muted mb-3" id="modalEmpPos" style="font-size: 0.95rem;"></p>
                    <span class="id-badge-role" id="modalEmpDeptBadge"></span>
                    <div class="id-info-grid">
                        <div class="id-info-item"><div class="id-icon-box"><i class="fas fa-id-card"></i></div><div class="lh-1"><small class="text-muted d-block mb-1" style="font-size: 0.7rem;">EMPLOYEE ID</small><span class="fw-bold text-dark" id="modalEmpNik"></span></div></div>
                        <div class="id-info-item"><div class="id-icon-box"><i class="fas fa-building"></i></div><div class="lh-1"><small class="text-muted d-block mb-1" style="font-size: 0.7rem;">DEPARTMENT</small><span class="fw-bold text-dark" id="modalEmpDept"></span></div></div>
                        <div class="id-info-item item-full-width"><div class="id-icon-box"><i class="fas fa-envelope"></i></div><div class="lh-1 flex-grow-1 overflow-hidden"><small class="text-muted d-block mb-1" style="font-size: 0.7rem;">OFFICIAL EMAIL</small><span class="fw-bold text-dark text-break" id="modalEmpEmail"></span></div></div>
                    </div>
                    <a href="#" class="btn-email-action" id="modalBtnEmail"><i class="fas fa-paper-plane"></i> Send Email</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="forumModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0">
                    <div><span class="badge bg-secondary mb-2" id="fmCat">Category</span><h5 class="modal-title fw-bold text-dark" id="fmTitle">Topic Title</h5></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <div class="d-flex gap-3 mb-4">
                        <img src="" id="fmAvatar" class="rounded-circle" width="50" height="50">
                        <div class="bg-white p-3 rounded shadow-sm w-100">
                            <div class="d-flex justify-content-between"><strong id="fmUser" class="text-dark">User</strong><small class="text-muted" id="fmTime">Time</small></div>
                            <p class="mt-2 mb-0 text-secondary text-dark">Ini adalah contoh isi pertanyaan diskusi.</p>
                        </div>
                    </div>
                    <p class="small fw-bold text-muted ms-2">Replies</p>
                    <div class="d-flex gap-3 mb-3">
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width:40px; height:40px;">AD</div>
                        <div class="bg-white p-3 rounded shadow-sm w-100"><small class="fw-bold d-block text-dark">Admin Support</small><p class="small text-muted m-0 text-dark">Halo, terima kasih pertanyaannya.</p></div>
                    </div>
                </div>
                <div class="modal-footer bg-white"><input type="text" class="form-control rounded-pill" placeholder="Write a reply..."><button class="btn btn-primary rounded-circle"><i class="fas fa-paper-plane"></i></button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bookingModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold"><i class="fas fa-calendar-alt text-orange me-2"></i>Book Meeting Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Showing availability for today: <b><?php echo date('d M Y'); ?></b></p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="book_room">
                        
                        <ul class="nav nav-pills mb-3 gap-2" id="pills-tab" role="tablist">
                            <?php $i=0; foreach($meeting_rooms as $rid => $info): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link border <?php echo $i===0?'active bg-orange':'text-dark'; ?>" id="pills-<?php echo $rid; ?>-tab" data-bs-toggle="pill" data-bs-target="#pills-<?php echo $rid; ?>" type="button" role="tab" onclick="selectRoom('<?php echo $rid; ?>')">
                                    <?php echo $info['name']; ?> <small class="opacity-75">(<?php echo $info['cap']; ?>)</small>
                                </button>
                            </li>
                            <?php $i++; endforeach; ?>
                        </ul>
                        <input type="hidden" name="room_id" id="selectedRoomId" value="R01">

                        <div class="tab-content border rounded p-3 bg-light" id="pills-tabContent">
                            <?php $i=0; foreach($meeting_rooms as $rid => $info): ?>
                            <div class="tab-pane fade <?php echo $i===0?'show active':''; ?>" id="pills-<?php echo $rid; ?>" role="tabpanel">
                                <h6 class="fw-bold mb-3 text-dark">Select Time Slot:</h6>
                                <div class="row g-2">
                                    <?php foreach($time_slots as $slot): 
                                        $is_booked = isset($today_bookings[$rid][$slot]);
                                        $booked_by = $is_booked ? $today_bookings[$rid][$slot] : '';
                                    ?>
                                    <div class="col-4 col-md-3">
                                        <label class="w-100">
                                            <div class="booking-slot <?php echo $is_booked ? 'booked' : ''; ?>">
                                                <?php if(!$is_booked): ?>
                                                    <input type="radio" name="time_slot" value="<?php echo $slot; ?>" required>
                                                <?php endif; ?>
                                                <div class="fs-5"><?php echo $slot; ?></div>
                                                <small class="d-block text-truncate" style="font-size:0.7rem;">
                                                    <?php echo $is_booked ? "Booked by $booked_by" : "Available"; ?>
                                                </small>
                                            </div>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php $i++; endforeach; ?>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold rounded-pill">Confirm Booking</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="postForumModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header border-0">
                    <h5 class="fw-bold">New Discussion Topic</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="post_forum">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">CATEGORY</label>
                            <select name="category" class="form-select">
                                <option>General</option>
                                <option>Network</option>
                                <option>Helpdesk</option>
                                <option>Lifestyle</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">TOPIC TITLE</label>
                            <input type="text" name="title" class="form-control" placeholder="What's on your mind?" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill">Post Topic</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="feedbackModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark">Feedback & Suggestions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="submit_feedback">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">YOUR NAME</label>
                            <input type="text" class="form-control" name="name" placeholder="Enter your name (Optional)" value="<?php echo $_SESSION['user_name'] ?? ''; ?>">
                        </div>
                        <label class="form-label fw-bold small text-muted">RATE YOUR EXPERIENCE</label>
                        <div class="star-rating mb-3" id="starRating">
                            <i class="fas fa-star" data-value="1"></i>
                            <i class="fas fa-star" data-value="2"></i>
                            <i class="fas fa-star" data-value="3"></i>
                            <i class="fas fa-star" data-value="4"></i>
                            <i class="fas fa-star" data-value="5"></i>
                            <input type="hidden" name="rating" id="ratingInput">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">MOST INTERESTING FEATURE</label>
                            <select class="form-select" name="feature">
                                <option selected disabled>Choose feature...</option>
                                <option>InsightBot AI Chat</option>
                                <option>HSE Updates</option>
                                <option>Employee Forum</option>
                                <option>Job Vacancy</option>
                                <option>Learning Hub</option>
                                <option>Video Gallery</option>
                                <option>ID Card & Search</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">SUGGESTIONS</label>
                            <textarea class="form-control" name="suggestion" rows="3" placeholder="Tell us how we can improve..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold">Submit Feedback</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="thankYouModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content rounded-4 border-0 text-center p-4">
                <div class="mb-3">
                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center mx-auto" style="width: 60px; height: 60px;">
                        <i class="fas fa-check fa-2x"></i>
                    </div>
                </div>
                <h5 class="fw-bold mb-1 text-dark">Thank You!</h5>
                <p class="text-muted small mb-3">Your feedback has been submitted successfully.</p>
                <button type="button" class="btn btn-success w-100 rounded-pill btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>

    <div class="modal fade" id="hseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 border-0 overflow-hidden">
                <div class="modal-header border-0 p-3" style="background: #f8f9fa;">
                    <h5 class="modal-title fw-bold text-dark" id="hseModalTitle">HSE Update</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 bg-dark">
                    <img src="" id="hseModalImg" class="w-100 h-100 object-fit-contain" style="max-height: 80vh;">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade spotlight-search" id="searchModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="spotlight-input-group d-flex align-items-center">
                    <i class="fas fa-search text-muted ms-3 fa-lg"></i>
                    <input type="text" id="searchInput" class="form-control spotlight-input" placeholder="Search apps, news, people..." autocomplete="off">
                    <button type="button" class="btn-close me-3" data-bs-dismiss="modal"></button>
                </div>
                <div class="spotlight-results" id="searchResults">
                    <div class="p-4">
                        <small class="text-muted fw-bold d-block mb-2">SUGGESTED</small>
                        <div class="row g-2">
                            <div class="col-6"><a href="#" class="search-item"><i class="fas fa-calendar-check text-primary"></i><span>Book Meeting Room</span></a></div>
                            <div class="col-6"><a href="#" class="search-item"><i class="fas fa-file-invoice-dollar text-success"></i><span>E-Claim</span></a></div>
                            <div class="col-6"><a href="#" class="search-item"><i class="fas fa-plane text-warning"></i><span>Cuti Online</span></a></div>
                            <div class="col-6"><a href="#" class="search-item"><i class="fas fa-book text-danger"></i><span>SOP Guidelines</span></a></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade modal-main-menu" id="menuModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold ps-2 text-dark">Main Menu</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                         <div class="col-12 col-md-6 col-lg-4"><div class="menu-category"><div class="menu-cat-icon ic-portal"><i class="fas fa-id-card-clip"></i></div><div><h6 class="text-orange fw-bold mb-2">Portal</h6><ul class="menu-links"><li><a href="#">Profile</a></li><li><a href="#">Medical Checkup (MCU)</a></li></ul></div></div></div>
                         <div class="col-12 col-md-6 col-lg-4"><div class="menu-category"><div class="menu-cat-icon ic-info"><i class="fas fa-bullhorn"></i></div><div><h6 class="text-orange fw-bold mb-2">Info Center</h6><ul class="menu-links"><li><a href="#">News & Articles</a></li><li><a href="#">Newsletter Archive</a></li><li><a href="#">Birthdate</a></li></ul></div></div></div>
                         <div class="col-12 col-md-6 col-lg-4"><div class="menu-category"><div class="menu-cat-icon ic-kpi"><i class="fas fa-chart-pie"></i></div><div><h6 class="text-orange fw-bold mb-2">KPI</h6><ul class="menu-links"><li><a href="#">Performance Appraisal</a></li><li><a href="#">FAQ</a></li></ul></div></div></div>
                         <div class="col-12 col-md-6 col-lg-4"><div class="menu-category"><div class="menu-cat-icon ic-guide"><i class="fas fa-bullseye"></i></div><div><h6 class="text-orange fw-bold mb-2">Employee Guidance</h6><ul class="menu-links"><li><a href="#">SOP</a></li><li><a href="#">Training</a></li></ul></div></div></div>
                         <div class="col-12 col-md-6 col-lg-4"><div class="menu-category"><div class="menu-cat-icon ic-hco"><i class="fas fa-house-user"></i></div><div><h6 class="text-orange fw-bold mb-2">HCO Center</h6><ul class="menu-links"><li><a href="#">Home</a></li></ul></div></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade modal-anniversary" id="welcomeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="btn-close-white-custom text-end mb-2" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </div>
                    <img src="<?php echo $popup_image_url; ?>" alt="Announcement" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <div class="chat-trigger" onclick="toggleChat()"><i class="fas fa-robot"></i></div>
    <div class="chat-window" id="chatBox">
        <div class="bg-white p-3 border-bottom d-flex justify-content-between align-items-center" style="background: linear-gradient(to right, #00A9B4, #008C95); color: white;">
            <div class="d-flex align-items-center gap-2"><i class="fas fa-bolt"></i> <span class="fw-bold">InsightBot AI</span></div>
            <i class="fas fa-times cursor-pointer" onclick="toggleChat()"></i>
        </div>
        <div class="flex-grow-1 p-3 overflow-auto bg-light d-flex flex-column gap-3" id="chatBody"></div>
        <div class="p-3 bg-white border-top d-flex gap-2">
            <input type="text" id="chatInput" class="form-control border-0 bg-light" placeholder="Ketik pesan..." onkeypress="handleChat(event)">
            <button class="btn btn-warning text-white rounded-circle shadow-sm" onclick="sendChat()" style="background-color: #00A9B4; border:none;"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

    <script>
        // --- [BARU] BOOKING ROOM HELPER ---
        function selectRoom(rid) { 
            document.getElementById('selectedRoomId').value = rid; 
        }

        // --- 1. INITIALIZATION & CHARTS ---
        document.addEventListener("DOMContentLoaded", function() {
            // Initialize Milestone Chart
            new Chart(document.getElementById('milestoneChart'), { 
                type: 'line', 
                data: { 
                    labels: ['J','F','M','A','M','J'], 
                    datasets: [{ 
                        data: [30, 45, 40, 60, 55, 80], 
                        borderColor: '#00A9B4', // Updated Chart Color
                        backgroundColor: 'rgba(0, 169, 180, 0.1)', 
                        fill: true, 
                        tension: 0.4, 
                        pointRadius: 0 
                    }] 
                }, 
                options: { 
                    responsive: true, 
                    maintainAspectRatio: false, 
                    plugins: { legend: false }, 
                    scales: { x: { display: false }, y: { display: false } } 
                } 
            });
            
            // --- LIVE CLOCK SCRIPT ---
function updateClock() {
    const now = new Date();
    const options = { 
        timeZone: 'Asia/Jakarta', 
        hour12: false, 
        hour: '2-digit', 
        minute: '2-digit', 
        second: '2-digit' 
    };
    // Format waktu Indonesia
    const timeString = now.toLocaleTimeString('id-ID', options).replace(/\./g, ':');
    
    const clockEl = document.getElementById('liveClock');
    if(clockEl) {
        clockEl.innerHTML = `<i class="far fa-clock me-1 text-secondary"></i> ${timeString} WIB`;
    }
}

// Jalankan fungsi setiap 1 detik
setInterval(updateClock, 1000);
updateClock(); // Jalankan langsung saat load agar tidak ada delay 1 detik

            // Chatbot Initial Greeting
            setTimeout(() => {
                const h = new Date().getHours(); 
                const greet = h<11?'Pagi':h<15?'Siang':h<18?'Sore':'Malam';
                const msg = `Halo Selamat ${greet}, Insight Crew! 👋<br>Selamat datang di Portal InsightSpace. Ada yang bisa saya bantu?`;
                const cb = document.getElementById('chatBody');
                const d = document.createElement('div'); 
                d.className = 'd-flex w-100';
                d.innerHTML = `<div class="msg-bubble msg-bot shadow-sm">${msg}</div>`;
                cb.appendChild(d);
            }, 1500);

            // Welcome Popup Logic
            <?php if (isset($_SESSION['show_welcome_popup']) && $_SESSION['show_welcome_popup'] === true): ?>
                var welcomeModal = new bootstrap.Modal(document.getElementById('welcomeModal'));
                welcomeModal.show();
                <?php unset($_SESSION['show_welcome_popup']); ?>
            <?php endif; ?>
            
            // Feedback Thank You Popup Logic
            <?php if (isset($_SESSION['show_thankyou_popup']) && $_SESSION['show_thankyou_popup'] === true): ?>
                var thankYouModal = new bootstrap.Modal(document.getElementById('thankYouModal'));
                thankYouModal.show();
                <?php unset($_SESSION['show_thankyou_popup']); ?>
            <?php endif; ?>

            // --- INIT DRAG & DROP ---
            initDashboardDragDrop();
            
            // --- [NEW] INIT TO-DO LIST & THEME ---
            loadTodos();
            
            // Check Theme from LocalStorage
            if(localStorage.getItem('fs_theme') === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
                document.getElementById('themeIcon').classList.remove('fa-moon');
                document.getElementById('themeIcon').classList.add('fa-sun');
            }
        });

        // --- DRAG & DROP LOGIC (PERSISTENT) ---
        let sortableInstances = []; // Store instances
        // Check saved lock state (default to false if not set)
        let isLayoutLocked = localStorage.getItem('fs_portal_layout_locked') === 'true';

        function initDashboardDragDrop() {
            const colLeft = document.getElementById('col_left');
            const colCenter = document.getElementById('col_center');
            const colRight = document.getElementById('col_right');

            // Fungsi untuk memuat posisi tersimpan
            loadDashboardLayout();
            
            // Update UI based on saved lock state
            updateLockUI();

            // Options for Sortable
            const sortableOptions = {
                group: 'dashboard', // Allows dragging between columns
                animation: 150,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                handle: '.card', // Drag using the card itself
                disabled: isLayoutLocked, // Apply initial lock state
                onSort: function (evt) {
                    saveDashboardLayout();
                }
            };

            sortableInstances.push(new Sortable(colLeft, sortableOptions));
            sortableInstances.push(new Sortable(colCenter, sortableOptions));
            sortableInstances.push(new Sortable(colRight, sortableOptions));
        }

        function toggleLayoutLock() {
            isLayoutLocked = !isLayoutLocked;
            localStorage.setItem('fs_portal_layout_locked', isLayoutLocked);
            
            // Update logic for all sortable instances
            sortableInstances.forEach(instance => {
                instance.option("disabled", isLayoutLocked);
            });

            updateLockUI();
        }

        function updateLockUI() {
            const btn = document.getElementById('btnLockLayout');
            const icon = btn.querySelector('i');
            const text = btn.querySelector('span');

            if(isLayoutLocked) {
                icon.className = 'fas fa-lock me-2 text-success';
                text.innerText = 'Unlock Layout';
                document.body.classList.add('layout-locked');
            } else {
                icon.className = 'fas fa-lock-open me-2 text-secondary';
                text.innerText = 'Lock Layout';
                document.body.classList.remove('layout-locked');
            }
        }

        // NEW: Audio Test
        function testNotificationSound() {
            // Simple beep/ding sound hosted online or base64
            const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
            audio.volume = 0.5;
            audio.play().catch(e => alert("Gagal memutar suara. Pastikan izin audio browser aktif."));
        }

        function saveDashboardLayout() {
            const layout = {
                col_left: getColumnOrder('col_left'),
                col_center: getColumnOrder('col_center'),
                col_right: getColumnOrder('col_right')
            };
            localStorage.setItem('fs_portal_layout', JSON.stringify(layout));
        }

        function getColumnOrder(colId) {
            const col = document.getElementById(colId);
            const cards = col.querySelectorAll('.card, .survey-card, .video-wrapper, .feedback-widget');
            const order = [];
            cards.forEach(card => {
                if(card.id) order.push(card.id);
            });
            return order;
        }

        function loadDashboardLayout() {
            const savedLayout = localStorage.getItem('fs_portal_layout');
            if (!savedLayout) return; // Use default HTML order if nothing saved

            const layout = JSON.parse(savedLayout);
            
            // Helper to move elements
            const moveCardsToCol = (cardIds, colId) => {
                const col = document.getElementById(colId);
                cardIds.forEach(id => {
                    const el = document.getElementById(id);
                    if (el) col.appendChild(el);
                });
            };

            moveCardsToCol(layout.col_left, 'col_left');
            moveCardsToCol(layout.col_center, 'col_center');
            moveCardsToCol(layout.col_right, 'col_right');
        }

        // Show Confirmation Modal
        function showResetModal() {
            const myModal = new bootstrap.Modal(document.getElementById('resetLayoutModal'));
            myModal.show();
        }

        // Confirm Action
        function confirmReset() {
            localStorage.removeItem('fs_portal_layout');
            localStorage.removeItem('fs_portal_layout_locked'); // Also reset lock
            location.reload();
        }
        
        // --- 2. MODAL TRIGGERS ---
        function showHseImage(url, title) {
            document.getElementById('hseModalImg').src = url;
            document.getElementById('hseModalTitle').innerText = title;
            new bootstrap.Modal(document.getElementById('hseModal')).show();
        }
        
        function openForumModal(data) {
            document.getElementById('fmCat').innerText = data.cat;
            document.getElementById('fmCat').className = 'badge mb-2 ' + data.c_bg;
            document.getElementById('fmTitle').innerText = data.t;
            document.getElementById('fmAvatar').src = data.av;
            document.getElementById('fmUser').innerText = data.u;
            document.getElementById('fmTime').innerText = data.tm;
            new bootstrap.Modal(document.getElementById('forumModal')).show();
        }

        // --- 3. NOTIFICATION TOGGLE ---
        function toggleNotif(e) { 
            if(e) e.stopPropagation();
            document.getElementById('notifDropdown').classList.toggle('show'); 
        }
        document.addEventListener('click', function(e) { 
            const btn = document.getElementById('notifBtn');
            const dropdown = document.getElementById('notifDropdown');
            if(btn && dropdown && !btn.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.classList.remove('show');
            }
        });

        // --- 4. CHATBOT LOGIC ---
        function toggleChat() { document.getElementById('chatBox').classList.toggle('active'); }
        function handleChat(e) { if(e.key==='Enter') sendChat(); }
        
        function sendChat() {
            const i = document.getElementById('chatInput');
            const v = i.value.trim(); 
            if(!v) return;
            
            addMsg(v, 'user'); 
            i.value = '';
            
            // Loading indicator
            const loaderId = 'loader-' + Date.now();
            const loadingHtml = `<div class="typing-indicator"><div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div></div>`;
            addMsg(loadingHtml, 'bot', loaderId);

            const f = new FormData(); 
            f.append('action', 'chat_gemini'); 
            f.append('message', v);
            
            fetch('index.php', {method: 'POST', body: f})
                .then(r => r.json())
                .then(d => {
                    const loader = document.getElementById(loaderId);
                    if(loader) loader.parentElement.remove();
                    const cleanHtml = marked.parse(d.reply);
                    addMsg(cleanHtml, 'bot');
                })
                .catch(e => {
                    const loader = document.getElementById(loaderId);
                    if(loader) loader.parentElement.remove();
                    addMsg("Maaf, terjadi kesalahan koneksi.", 'bot');
                });
        }

        function addMsg(html, role, id = null) {
            const cb = document.getElementById('chatBody');
            const d = document.createElement('div');
            d.className = 'd-flex w-100';
            const bubbleClass = role === 'user' ? 'msg-user' : 'msg-bot shadow-sm';
            d.innerHTML = `<div class="msg-bubble ${bubbleClass}" ${id ? 'id="'+id+'"' : ''}>${html}</div>`;
            cb.appendChild(d);
            cb.scrollTop = cb.scrollHeight;
        }

        // --- 5. SPOTLIGHT SEARCH LOGIC ---
        const sData = [
            {t:'Microsoft 365',c:'App'}, {t:'Company Profile',c:'Video'},
            {t:'Bernadus Bayu',c:'Person'}, {t:'InsightSpace News',c:'News'}
        ];
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const v=e.target.value.toLowerCase(), r=document.getElementById('searchResults'); 
            r.innerHTML='';
            if(!v) return r.innerHTML='<div class="p-4"><small class="text-muted fw-bold d-block mb-2">SUGGESTED</small><div class="row g-2"><div class="col-6"><a href="#" class="search-item"><i class="fas fa-calendar-check text-primary"></i><span>Book Meeting Room</span></a></div><div class="col-6"><a href="#" class="search-item"><i class="fas fa-file-invoice-dollar text-success"></i><span>E-Claim</span></a></div><div class="col-6"><a href="#" class="search-item"><i class="fas fa-plane text-warning"></i><span>Cuti Online</span></a></div><div class="col-6"><a href="#" class="search-item"><i class="fas fa-book text-danger"></i><span>SOP Guidelines</span></a></div></div></div>';
            
            sData.filter(i=>i.t.toLowerCase().includes(v)).forEach(i=>{ 
                r.innerHTML+=`<a href="#" class="search-item"><i class="fas fa-search text-muted"></i><div><div class="fw-bold small text-dark">${i.t}</div><small class="text-muted">${i.c}</small></div></a>`; 
            });
        });
        document.getElementById('searchModal').addEventListener('shown.bs.modal', ()=>document.getElementById('searchInput').focus());

        // --- 6. EMPLOYEE SEARCH & ID CARD LOGIC ---
        const empInput = document.getElementById('modalEmpSearchInput');
        const empResults = document.getElementById('modalEmpSearchResults');
        const empDetailModal = new bootstrap.Modal(document.getElementById('employeeModal'));
        const empSearchModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('employeeSearchModal'));

        document.querySelector('.emp-trigger-card').addEventListener('click', function() {
            empResults.innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-user-friends fa-3x mb-3 opacity-25"></i><p>Start typing to find your colleague...</p></div>'; 
            empSearchModal.show();
        });

        empInput.addEventListener('input', function() {
            const term = this.value.trim();
            if (term.length < 2) return;
            const fd = new FormData();
            fd.append('action', 'search_employee');
            fd.append('term', term);
            
            fetch('index.php', { method: 'POST', body: fd }).then(r => r.json()).then(data => {
                    empResults.innerHTML = '';
                    if (data.results.length > 0) {
                        const list = document.createElement('div');
                        list.className = 'bg-white rounded';
                        data.results.forEach(emp => {
                            const div = document.createElement('div');
                            div.className = 'emp-item-lg';
                            div.innerHTML = `<img src="${emp.img}"><div><small class="fw-bold d-block text-dark fs-6">${emp.name}</small><small class="text-muted">${emp.pos} - ${emp.dept}</small></div><i class="fas fa-chevron-right ms-auto text-muted"></i>`;
                            
                            div.onclick = () => {
                                bootstrap.Modal.getInstance(document.getElementById('employeeSearchModal')).hide();
                                document.getElementById('modalEmpImg').src = emp.img;
                                document.getElementById('modalEmpName').innerText = emp.name;
                                document.getElementById('modalEmpPos').innerText = emp.pos;
                                document.getElementById('modalEmpDept').innerText = emp.dept;
                                document.getElementById('modalEmpDeptBadge').innerText = emp.dept;
                                document.getElementById('modalEmpNik').innerText = emp.nik;
                                document.getElementById('modalEmpEmail').innerText = emp.email;
                                document.getElementById('modalBtnEmail').href = "mailto:" + emp.email;
                                empDetailModal.show();
                            };
                            list.appendChild(div);
                        });
                        empResults.appendChild(list);
                    } else { empResults.innerHTML = '<div class="p-5 text-center text-muted">No colleagues found.</div>'; }
                });
        });
        document.getElementById('employeeSearchModal').addEventListener('shown.bs.modal', ()=>empInput.focus());

        // --- 7. STAR RATING ---
        const stars = document.querySelectorAll('.star-rating i');
        const ratingInput = document.getElementById('ratingInput');
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const val = this.getAttribute('data-value');
                ratingInput.value = val;
                stars.forEach(s => {
                    if(s.getAttribute('data-value') <= val) s.classList.add('selected');
                    else s.classList.remove('selected');
                });
            });
        });

        // --- 8. FULLSCREEN LOGIC ---
        function toggleFullscreen() {
            const elem = document.documentElement;
            const btnIcon = document.querySelector('#btnFullscreen i');

            if (!document.fullscreenElement) {
                elem.requestFullscreen().catch(err => {
                    alert(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
                });
            } else {
                document.exitFullscreen();
            }
        }

        document.addEventListener('fullscreenchange', () => {
            const btnIcon = document.querySelector('#btnFullscreen i');
            if (document.fullscreenElement) {
                btnIcon.classList.remove('fa-expand');
                btnIcon.classList.add('fa-compress');
            } else {
                btnIcon.classList.remove('fa-compress');
                btnIcon.classList.add('fa-expand');
            }
        });

        // --- [NEW] 9. TO-DO LIST LOGIC (LOCALSTORAGE) ---
        function loadTodos() {
            const todos = JSON.parse(localStorage.getItem('fs_todos')) || [];
            const list = document.getElementById('todoList');
            list.innerHTML = '';
            if(todos.length === 0) {
                list.innerHTML = '<li class="list-group-item text-center text-muted border-0">No active tasks</li>';
                return;
            }
            todos.forEach((t, idx) => {
                const li = document.createElement('li');
                li.className = 'list-group-item border-0 d-flex justify-content-between align-items-center px-0';
                li.innerHTML = `
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" ${t.done?'checked':''} onchange="toggleTodo(${idx})">
                        <label class="form-check-label ${t.done?'text-decoration-line-through text-muted':''}">${t.text}</label>
                    </div>
                    <button class="btn btn-link btn-sm text-danger p-0" onclick="deleteTodo(${idx})"><i class="fas fa-times"></i></button>
                `;
                list.appendChild(li);
            });
        }

        function addTodo() {
            const input = document.getElementById('todoInput');
            const val = input.value.trim();
            if(!val) return;
            const todos = JSON.parse(localStorage.getItem('fs_todos')) || [];
            todos.push({text: val, done: false});
            localStorage.setItem('fs_todos', JSON.stringify(todos));
            input.value = '';
            loadTodos();
        }

        function toggleTodo(idx) {
            const todos = JSON.parse(localStorage.getItem('fs_todos'));
            todos[idx].done = !todos[idx].done;
            localStorage.setItem('fs_todos', JSON.stringify(todos));
            loadTodos();
        }

        function deleteTodo(idx) {
            const todos = JSON.parse(localStorage.getItem('fs_todos'));
            todos.splice(idx, 1);
            localStorage.setItem('fs_todos', JSON.stringify(todos));
            loadTodos();
        }

        // Handle Enter key on Todo Input
        document.getElementById('todoInput').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') addTodo();
        });

        // --- [NEW] 10. DARK MODE TOGGLE ---
        function toggleTheme() {
            const html = document.documentElement;
            const icon = document.getElementById('themeIcon');
            const current = html.getAttribute('data-theme');
            
            if(current === 'dark') {
                html.removeAttribute('data-theme');
                localStorage.setItem('fs_theme', 'light');
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            } else {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('fs_theme', 'dark');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            }
        }

        // --- 11. SCRIPT UNTUK DICTIONARY SEARCH ---
        function filterDictionary() {
            var input, filter, accordion, item, btn, txtValue;
            input = document.getElementById('dictSearch');
            filter = input.value.toUpperCase();
            accordion = document.getElementById("dictAccordion");
            item = accordion.getElementsByClassName('dict-item');

            for (var i = 0; i < item.length; i++) {
                btn = item[i].getElementsByTagName("button")[0];
                txtValue = btn.textContent || btn.innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    item[i].style.display = "";
                } else {
                    item[i].style.display = "none";
                }
            }
        }
    </script>
    <div class="modal fade" id="coverageMapModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 overflow-hidden">
            <div class="modal-header border-0 pb-0 pt-3 px-4">
                <h5 class="fw-bold text-dark"><i class="fas fa-map-marked-alt text-success me-2"></i>Coverage Area FiberStar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 mt-3">
                <div class="ratio ratio-16x9 bg-light">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d126920.2474276435!2d106.845599!3d-6.2297465!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sen!2sid!4v1633000000000!5m2!1sen!2sid" style="border:0; filter: grayscale(20%) contrast(1.2);" allowfullscreen="" loading="lazy"></iframe>
                    
                    <div class="position-absolute bottom-0 start-0 m-3 p-2 bg-white rounded shadow-sm border" style="max-width: 200px; opacity: 0.9;">
                        <small class="fw-bold d-block mb-1">Legenda Jaringan</small>
                        <div class="d-flex align-items-center gap-2 mb-1"><span class="badge bg-success rounded-circle p-1"> </span> <small style="font-size:0.7rem">Active (Fiber Ready)</small></div>
                        <div class="d-flex align-items-center gap-2"><span class="badge bg-warning rounded-circle p-1"> </span> <small style="font-size:0.7rem">Deployment Phase</small></div>
                    </div>
                </div>
                
                <div class="p-4 bg-white">
                    <h6 class="fw-bold mb-3"><i class="fas fa-wifi text-primary me-2"></i>Area Tercover (Highlights)</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check-circle me-1"></i> Jakarta Selatan</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check-circle me-1"></i> Jakarta Pusat</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check-circle me-1"></i> Tangerang Selatan</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check-circle me-1"></i> Bekasi Barat</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check-circle me-1"></i> Surabaya Timur</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fas fa-check-circle me-1"></i> Medan Kota</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="fas fa-tools me-1"></i> Depok (Coming Soon)</span>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="fas fa-tools me-1"></i> Bogor (Coming Soon)</span>
                    </div>
                    <div class="alert alert-info d-flex align-items-center gap-2 mt-3 mb-0 py-2 small">
                        <i class="fas fa-info-circle"></i> Data di atas adalah simulasi dummy. Untuk cek alamat spesifik, hubungi tim Data Services.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>