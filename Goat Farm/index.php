<?php
$is_post = ($_SERVER['REQUEST_METHOD'] === 'POST');
$output = "";
$show_modal = false; // ডিফল্ট false – পেজ লোড হলে মডাল খোলে না

if ($is_post) {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        switch ($action) {
            case 'reboot':
                $output = "System is rebooting... Please wait.";
                $show_modal = true;
                // ব্যাকগ্রাউন্ডে রান করা হচ্ছে যাতে ব্রাউজার রেসপন্স পাঠানোর সময় পায়
                shell_exec("sudo reboot > /dev/null 2>&1 &");
                break;
            case 'shutdown':
                $output = "System is shutting down... Connection closing.";
                $show_modal = true;
                shell_exec("sudo poweroff > /dev/null 2>&1 &");
                break;
            case 'filemanager':
                shell_exec("export DISPLAY=:0; sudo pcmanfm > /dev/null 2>&1 &");
                $output = "File Manager launched on screen.";
                $show_modal = true;
                break;
            case 'terminal':
                shell_exec("export DISPLAY=:0; sudo lxterminal > /dev/null 2>&1 &");
                $output = "LXTerminal launched on screen.";
                $show_modal = true;
                break;
        }
    } elseif (isset($_POST['custom_command'])) {
        $cmd = trim($_POST['custom_command']);
        if (!empty($cmd)) {
            /* 
               নিরাপত্তা সতর্কতা: shell_exec-এ সরাসরি ইউজার ইনপুট দেওয়া ঝুঁকিপূর্ণ।
               নিশ্চিত করুন এই ড্যাশবোর্ডটি যেন কোনো পাবলিক আইপি বা ইন্টারনেটে সরাসরি উন্মুক্ত না থাকে।
               শুধুমাত্র লোকাল নেটওয়ার্কে (LAN) এটি ব্যবহার করা নিরাপদ।
            */
            $output = shell_exec("sudo " . $cmd . " 2>&1");
            if (empty($output)) {
                $output = "Command executed successfully (no output returned).";
            }
        } else {
            $output = "Error: Command cannot be empty.";
        }
        $show_modal = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Goat OS - Smart Farm Dashboard</title>
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ===== গ্লোবাল রিসেট ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            height: 100%;
            height: 100dvh;
            font-family: 'Inter', sans-serif;
            background: #0b0e17;
            color: #f0f4ff;
            overflow: hidden;
        }

        /* ===== স্প্ল্যাশ স্ক্রিন ===== */
        #splash-screen {
            position: fixed;
            inset: 0;
            z-index: 9999;
            background: linear-gradient(145deg, #0b0e17 0%, #1a1f2e 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: opacity 0.8s ease, visibility 0.8s ease;
            padding: 20px;
            padding-bottom: env(safe-area-inset-bottom, 20px);
        }
        #splash-screen.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        #splash-screen .logo-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
            margin-bottom: 24px;
        }
        #splash-screen .logo-wrapper svg {
            width: 100%;
            height: 100%;
            filter: drop-shadow(0 8px 30px rgba(74, 108, 247, 0.3));
            animation: float 2.5s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        #splash-screen h1 {
            font-size: 2.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4a6cf7, #6d8cff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -1px;
            margin-bottom: 4px;
        }
        #splash-screen p {
            font-size: 1rem;
            color: rgba(255,255,255,0.5);
            font-weight: 300;
            letter-spacing: 2px;
            margin-bottom: 32px;
        }
        .loader {
            width: 200px;
            height: 3px;
            background: rgba(255,255,255,0.06);
            border-radius: 4px;
            overflow: hidden;
        }
        .loader-bar {
            width: 0%;
            height: 100%;
            background: linear-gradient(90deg, #4a6cf7, #8aabff);
            border-radius: 4px;
            animation: loading 2s ease-in-out forwards;
        }
        @keyframes loading {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        /* ===== ডেস্কটপ UI ===== */
        #desktop {
            display: none;
            width: 100%;
            height: 100%;
            height: 100dvh;
            flex-direction: column;
            background: radial-gradient(ellipse at 50% 0%, #1a1f2e 0%, #0b0e17 100%);
            position: relative;
        }
        #desktop.active {
            display: flex;
        }

        #desktop::before {
            content: '';
            position: absolute;
            top: -30%;
            left: -10%;
            width: 60%;
            height: 80%;
            background: radial-gradient(circle, rgba(74,108,247,0.06) 0%, transparent 70%);
            pointer-events: none;
        }
        #desktop::after {
            content: '';
            position: absolute;
            bottom: -20%;
            right: -10%;
            width: 50%;
            height: 60%;
            background: radial-gradient(circle, rgba(109,140,255,0.04) 0%, transparent 70%);
            pointer-events: none;
        }

        .desktop-area {
            flex: 1;
            padding: 24px 20px 12px 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            grid-auto-rows: 140px;
            gap: 24px 16px;
            align-content: flex-start;
            overflow-y: auto;
            position: relative;
            z-index: 1;
            padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px));
        }

        .desktop-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 20px;
            padding: 16px 8px;
            transition: all 0.25s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            cursor: pointer;
            color: #e8edff;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }
        .desktop-icon:hover {
            background: rgba(255,255,255,0.07);
            border-color: rgba(74,108,247,0.3);
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(74,108,247,0.15);
        }
        .desktop-icon:active {
            transform: scale(0.95);
        }
        .desktop-icon svg {
            width: 52px;
            height: 52px;
            margin-bottom: 10px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
        }
        .desktop-icon span {
            font-size: 0.8rem;
            font-weight: 500;
            text-align: center;
            line-height: 1.3;
            color: rgba(255,255,255,0.7);
            letter-spacing: 0.3px;
        }
        .desktop-icon-form {
            display: contents;
        }
        .desktop-icon-form button {
            background: none;
            border: none;
            padding: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #e8edff;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
        }

        /* ===== টাস্কবার ===== */
        .taskbar {
            height: 56px;
            background: rgba(11, 14, 23, 0.7);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            flex-shrink: 0;
            position: relative;
            z-index: 2;
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }
        .taskbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            color: rgba(255,255,255,0.8);
        }
        .taskbar-left svg {
            width: 28px;
            height: 28px;
        }
        .taskbar-left .brand {
            font-weight: 700;
            background: linear-gradient(135deg, #4a6cf7, #8aabff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .taskbar-right {
            display: flex;
            align-items: center;
            gap: 18px;
            font-size: 0.85rem;
            font-weight: 500;
            color: rgba(255,255,255,0.6);
        }
        .taskbar-right .time {
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .taskbar-right .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 4px;
        }
        .taskbar-right .status-dot.online {
            background: #4ade80;
            box-shadow: 0 0 8px rgba(74, 222, 128, 0.3);
        }
        .taskbar-right .status-dot.offline {
            background: #f87171;
            box-shadow: 0 0 8px rgba(248, 113, 113, 0.3);
        }

        /* ===== মডাল (কমান্ড কনসোল) ===== */
        .modal-content {
            background: rgba(11, 14, 23, 0.85) !important;
            backdrop-filter: blur(30px) !important;
            border: 1px solid rgba(255,255,255,0.08) !important;
            border-radius: 24px !important;
            color: #e8edff !important;
            box-shadow: 0 24px 80px rgba(0,0,0,0.6) !important;
        }
        .modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.06) !important;
            padding: 18px 24px !important;
        }
        .modal-header .modal-title {
            font-weight: 600;
            color: #fff;
        }
        .modal-body {
            padding: 24px !important;
        }
        .console-output {
            background: rgba(0,0,0,0.3) !important;
            color: #b0c4ff !important;
            border: 1px solid rgba(255,255,255,0.06) !important;
            border-radius: 12px !important;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            padding: 12px 16px;
            width: 100%;
            height: 200px;
            resize: none;
        }
        .cmd-input {
            background: rgba(255,255,255,0.05) !important;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.08) !important;
            border-radius: 12px !important;
            color: #e8edff !important;
            padding: 12px 16px;
            width: 100%;
            font-size: 0.9rem;
        }
        .cmd-input:focus {
            border-color: #4a6cf7 !important;
            box-shadow: 0 0 0 3px rgba(74,108,247,0.15) !important;
            outline: none;
        }
        .cmd-input::placeholder {
            color: rgba(255,255,255,0.25);
        }
        .btn-primary {
            background: #4a6cf7 !important;
            border: none !important;
            border-radius: 12px !important;
            padding: 10px 28px !important;
            font-weight: 600;
            color: #fff !important;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background: #5d7cf8 !important;
            transform: scale(1.02);
        }

        /* ===== মোবাইল রেসপন্সিভ ===== */
        @media (max-width: 576px) {
            #splash-screen h1 {
                font-size: 2rem;
            }
            #splash-screen .logo-wrapper {
                width: 80px;
                height: 80px;
            }
            .desktop-area {
                grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
                grid-auto-rows: 110px;
                gap: 16px 12px;
                padding: 16px 12px 8px 12px;
            }
            .desktop-icon {
                padding: 12px 4px;
                border-radius: 16px;
            }
            .desktop-icon svg {
                width: 38px;
                height: 38px;
            }
            .desktop-icon span {
                font-size: 0.65rem;
            }
            .taskbar {
                height: 48px;
                padding: 0 12px;
            }
            .taskbar-left .brand {
                font-size: 0.8rem;
            }
            .taskbar-left svg {
                width: 22px;
                height: 22px;
            }
            .taskbar-right {
                font-size: 0.7rem;
                gap: 10px;
            }
            .taskbar-right .time {
                font-size: 0.8rem;
            }
            .modal-content {
                border-radius: 16px !important;
                margin: 12px;
            }
        }
        @media (min-width: 577px) and (max-width: 992px) {
            .desktop-area {
                grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            }
        }

        .desktop-area::-webkit-scrollbar {
            width: 4px;
        }
        .desktop-area::-webkit-scrollbar-track {
            background: transparent;
        }
        .desktop-area::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
        }
        .desktop-area::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>

    <!-- ===== স্প্ল্যাশ স্ক্রিন ===== -->
    <!-- POST রিকোয়েস্ট হলে স্প্ল্যাশ স্ক্রিন ক্লাসটিকে আগেই হিডেন (hidden) করে দেওয়া হচ্ছে -->
    <div id="splash-screen" class="<?php echo $is_post ? 'hidden' : ''; ?>">
        <div class="logo-wrapper">
            <svg viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="60" cy="60" r="58" fill="rgba(74,108,247,0.08)" stroke="rgba(74,108,247,0.2)" stroke-width="2"/>
                <path d="M60 25C46 25 35 35 35 48C35 61 46 70 60 70C74 70 85 61 85 48C85 35 74 25 60 25Z" fill="url(#goatGrad)"/>
                <path d="M60 38C53 38 47 44 47 51C47 58 53 63 60 63C67 63 73 58 73 51C73 44 67 38 60 38Z" fill="white"/>
                <path d="M50 56L55 61L68 48" stroke="white" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="42" cy="47" r="4" fill="white"/>
                <circle cx="78" cy="47" r="4" fill="white"/>
                <defs>
                    <linearGradient id="goatGrad" x1="35" y1="25" x2="85" y2="70" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#4a6cf7"/>
                        <stop offset="1" stop-color="#8aabff"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>
        <h1>Goat OS</h1>
        <p>SMART FARM MANAGEMENT</p>
        <div class="loader">
            <div class="loader-bar"></div>
        </div>
    </div>

    <!-- ===== ডেস্কটপ UI ===== -->
    <div id="desktop">
        <div class="desktop-area">
            
            <!-- 1. Goat Management -->
            <a href="app.php" class="desktop-icon">
                <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="8" y="8" width="48" height="48" rx="14" fill="url(#appGrad1)" stroke="rgba(255,255,255,0.08)" stroke-width="1.5"/>
                    <path d="M32 20C26 20 21 25 21 31C21 37 26 42 32 42C38 42 43 37 43 31C43 25 38 20 32 20Z" fill="white" opacity="0.9"/>
                    <path d="M32 26C28.5 26 25.5 29 25.5 32.5C25.5 36 28.5 39 32 39C35.5 39 38.5 36 38.5 32.5C38.5 29 35.5 26 32 26Z" fill="#4a6cf7"/>
                    <circle cx="27" cy="31" r="2" fill="white"/>
                    <circle cx="37" cy="31" r="2" fill="white"/>
                    <defs>
                        <linearGradient id="appGrad1" x1="8" y1="8" x2="56" y2="56" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#4a6cf7"/>
                            <stop offset="1" stop-color="#6d8cff"/>
                        </linearGradient>
                    </defs>
                </svg>
                <span>Goat Management</span>
            </a>

            <!-- 2. File Manager -->
            <form method="POST" class="desktop-icon-form">
                <input type="hidden" name="action" value="filemanager">
                <button type="submit" class="desktop-icon">
                    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="8" y="8" width="48" height="48" rx="14" fill="#1e293b" stroke="rgba(255,255,255,0.08)" stroke-width="1.5"/>
                        <path d="M44 20H32L28 16H20C17.79 16 16 17.79 16 20V44C16 46.21 17.79 48 20 48H44C46.21 48 48 46.21 48 44V24C48 21.79 46.21 20 44 20Z" fill="#f59e0b" opacity="0.9"/>
                        <path d="M38 28H26V31H38V28Z" fill="white"/>
                        <path d="M38 34H26V37H38V34Z" fill="white" opacity="0.6"/>
                    </svg>
                    <span>File Manager</span>
                </button>
            </form>

            <!-- 3. Terminal -->
            <form method="POST" class="desktop-icon-form">
                <input type="hidden" name="action" value="terminal">
                <button type="submit" class="desktop-icon">
                    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="8" y="8" width="48" height="48" rx="14" fill="#1e293b" stroke="rgba(255,255,255,0.08)" stroke-width="1.5"/>
                        <rect x="16" y="18" width="32" height="28" rx="4" fill="#10b981" opacity="0.9"/>
                        <path d="M24 28L29 32L24 36" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M32 36H40" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                    </svg>
                    <span>Terminal</span>
                </button>
            </form>

            <!-- 4. Run Command -->
            <button type="button" class="desktop-icon" data-bs-toggle="modal" data-bs-target="#cmdModal">
                <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="8" y="8" width="48" height="48" rx="14" fill="#1e293b" stroke="rgba(255,255,255,0.08)" stroke-width="1.5"/>
                    <rect x="16" y="18" width="32" height="28" rx="4" fill="#3b82f6" opacity="0.9"/>
                    <path d="M24 28L29 32L24 36" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M32 36H40" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                </svg>
                <span>Run Command</span>
            </button>

            <!-- 5. Reboot -->
            <form method="POST" class="desktop-icon-form" onsubmit="return confirm('Reboot the system?');">
                <input type="hidden" name="action" value="reboot">
                <button type="submit" class="desktop-icon">
                    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="8" y="8" width="48" height="48" rx="14" fill="#1e293b" stroke="rgba(255,255,255,0.08)" stroke-width="1.5"/>
                        <path d="M32 20V16L26 22L32 28V24C36.42 24 40 27.58 40 32C40 33.98 39.18 35.8 37.88 37.1L39.92 39.14C41.52 37.38 42.5 35.08 42.5 32.5C42.5 26.7 37.8 22 32 22Z" fill="#f87171"/>
                        <path d="M24 32C24 27.58 27.58 24 32 24V20C25.37 20 20 25.37 20 32C20 38.63 25.37 44 32 44V48L38 42L32 36V40C27.58 40 24 36.42 24 32Z" fill="#f87171"/>
                    </svg>
                    <span>Reboot</span>
                </button>
            </form>

            <!-- 6. Power Off -->
            <form method="POST" class="desktop-icon-form" onsubmit="return confirm('Shutdown the system?');">
                <input type="hidden" name="action" value="shutdown">
                <button type="submit" class="desktop-icon">
                    <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="8" y="8" width="48" height="48" rx="14" fill="#1e293b" stroke="rgba(255,255,255,0.08)" stroke-width="1.5"/>
                        <path d="M33 16H31V34H33V16Z" fill="#ef4444"/>
                        <path d="M42.42 18.58L40.64 20.36C43.56 22.88 45.5 26.38 45.5 30.5C45.5 38.6 38.9 45.2 30.8 45.2C22.7 45.2 16.1 38.6 16.1 30.5C16.1 26.38 18.04 22.88 20.96 20.36L19.18 18.58C15.68 21.56 13.5 25.78 13.5 30.5C13.5 39.94 21.46 48 30.8 48C40.14 48 48.1 39.94 48.1 30.5C48.1 25.78 45.92 21.56 42.42 18.58Z" fill="#ef4444"/>
                    </svg>
                    <span>Power Off</span>
                </button>
            </form>

        </div>

        <!-- ===== টাস্কবার ===== -->
        <div class="taskbar">
            <div class="taskbar-left">
                <svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="14" cy="14" r="12" fill="#4a6cf7"/>
                    <path d="M14 8C11.79 8 10 9.79 10 12C10 14.21 11.79 16 14 16C16.21 16 18 14.21 18 12C18 9.79 16.21 8 14 8Z" fill="white"/>
                    <path d="M14 18C10.13 18 7 20.69 7 24H21C21 20.69 17.87 18 14 18Z" fill="white" opacity="0.6"/>
                </svg>
                <span class="brand">Goat OS</span>
            </div>
            <div class="taskbar-right">
                <span id="network-status">
                    <span class="status-dot online" id="network-dot"></span>
                    <span id="network-text">Online</span>
                </span>
                <span id="date-display"></span>
                <span class="time" id="time-display"></span>
            </div>
        </div>
    </div>

    <!-- ===== কমান্ড মডাল ===== -->
    <div class="modal fade" id="cmdModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-terminal me-2"></i>System Console</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <textarea class="console-output" readonly placeholder="Command output will appear here..."><?php echo htmlspecialchars($output); ?></textarea>
                    <form method="POST" class="mt-3" id="cmdForm">
                        <div class="d-flex gap-2">
                            <input type="text" name="custom_command" class="cmd-input" placeholder="Type a command... (e.g. ls, df -h, free -m)" autocomplete="off">
                            <button type="submit" class="btn btn-primary">Run</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== স্প্ল্যাশ → ডেস্কটপ =====
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!$is_post): ?>
            // প্রথমবার পেজ লোড হলে স্প্ল্যাশ স্ক্রিন দেখাবে
            setTimeout(function() {
                document.getElementById('splash-screen').classList.add('hidden');
                document.getElementById('desktop').classList.add('active');
            }, 2200);
            <?php else: ?>
            // POST রিকোয়েস্ট সাবমিট হলে স্প্ল্যাশ স্ক্রিন স্কিপ হবে
            document.getElementById('splash-screen').style.display = 'none';
            document.getElementById('desktop').classList.add('active');
            <?php endif; ?>
        });

        // ===== রিয়েল টাইম ক্লক =====
        function updateClock() {
            const now = new Date();
            document.getElementById('date-display').textContent = now.toLocaleDateString('en-US', { 
                year: 'numeric', month: 'short', day: 'numeric' 
            });
            document.getElementById('time-display').textContent = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', minute: '2-digit', second: '2-digit' 
            });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // ===== নেটওয়ার্ক স্ট্যাটাস =====
        function updateNetwork() {
            const dot = document.getElementById('network-dot');
            const text = document.getElementById('network-text');
            if (navigator.onLine) {
                dot.className = 'status-dot online';
                text.textContent = 'Online';
            } else {
                dot.className = 'status-dot offline';
                text.textContent = 'Offline';
            }
        }
        window.addEventListener('online', updateNetwork);
        window.addEventListener('offline', updateNetwork);
        updateNetwork();

        // ===== মডাল ওপেন হলে অটোফোকাস =====
        const cmdModal = document.getElementById('cmdModal');
        cmdModal.addEventListener('shown.bs.modal', function() {
            document.querySelector('.cmd-input').focus();
        });

        // ===== ফর্ম জমার পর মডাল অটোমেটিক্যালি ওপেন করা =====
        <?php if ($show_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            var modal = new bootstrap.Modal(document.getElementById('cmdModal'));
            modal.show();
            
            // কনসোলের টেক্সট স্বয়ংক্রিয়ভাবে স্ক্রল করে একদম নিচে নামানো
            const consoleOutput = document.querySelector('.console-output');
            if (consoleOutput) {
                consoleOutput.scrollTop = consoleOutput.scrollHeight;
            }
            
            // মডাল অ্যানিমেশন শেষ হওয়ার পর ইনপুটে অটো-ফোকাস করা
            setTimeout(() => {
                document.querySelector('.cmd-input').focus();
            }, 500);
        });
        <?php endif; ?>
    </script>
</body>
</html>