<?php
$output = "";
$show_modal = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        switch ($action) {
            case 'reboot':
                $output = "System is rebooting...";
                $show_modal = true;
                shell_exec("sudo reboot");
                break;
            case 'shutdown':
                $output = "System is shutting down...";
                $show_modal = true;
                shell_exec("sudo poweroff");
                break;
            case 'filemanager':
                shell_exec("export DISPLAY=:0; sudo pcmanfm > /dev/null 2>&1 &");
                $output = "File Manager launched on screen.";
                break;
            case 'terminal':
                shell_exec("export DISPLAY=:0; sudo lxterminal > /dev/null 2>&1 &");
                $output = "LXTerminal launched on screen.";
                break;
        }
    } elseif (isset($_POST['custom_command'])) {
        $cmd = trim($_POST['custom_command']);
        if (!empty($cmd)) {
            $output = shell_exec("sudo " . $cmd . " 2>&1");
            $show_modal = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goat OS - Smart Farm Dashboard</title>
    <style>
        :root {
            --bg-gradient: radial-gradient(circle, #1e293b 0%, #0f172a 100%);
            --taskbar-bg: rgba(15, 23, 42, 0.85);
            --icon-hover: rgba(255, 255, 255, 0.1);
            --modal-bg: #1e293b;
            --text-color: #f1f5f9;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-gradient);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            user-select: none;
        }

        /* Desktop Area */
        .desktop {
            flex: 1;
            padding: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            grid-auto-rows: 120px;
            gap: 25px;
            align-content: flex-start;
            z-index: 1;
        }

        /* Desktop Shortcuts */
        .desktop-icon {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 110px;
            height: 110px;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-color);
            text-align: center;
            transition: all 0.2s ease;
            background: transparent;
            border: 1px solid transparent;
            outline: none;
        }

        .desktop-icon:hover {
            background: var(--icon-hover);
            border-color: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transform: translateY(-2px);
        }

        .desktop-icon svg {
            width: 50px;
            height: 50px;
            margin-bottom: 8px;
            filter: drop-shadow(0 2px 5px rgba(0,0,0,0.3));
        }

        .desktop-icon span {
            font-size: 13px;
            font-weight: 500;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.8);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Taskbar */
        .taskbar {
            height: 48px;
            background: var(--taskbar-bg);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            z-index: 1000;
        }

        .taskbar-left {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: bold;
            font-size: 15px;
            letter-spacing: 0.5px;
        }

        .taskbar-left svg {
            width: 22px;
            height: 22px;
        }

        .taskbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            font-weight: 500;
        }

        /* Floating Console Window (Modal) */
        .modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 650px;
            background: var(--modal-bg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            z-index: 2000;
            overflow: hidden;
        }

        .modal.active {
            display: block;
        }

        .modal-header {
            background: rgba(0, 0, 0, 0.3);
            padding: 10px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-title {
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-close {
            background: #ef4444;
            border: none;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            cursor: pointer;
        }

        .modal-close:hover {
            background: #dc2626;
        }

        .modal-body {
            padding: 15px;
        }

        .console-output {
            width: 100%;
            height: 200px;
            background: #000000;
            color: #38bdf8;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            padding: 10px;
            font-family: 'Courier New', Courier, monospace;
            box-sizing: border-box;
            resize: none;
            font-size: 13px;
            margin-bottom: 15px;
        }

        .cmd-form {
            display: flex;
            gap: 10px;
        }

        .cmd-input {
            flex: 1;
            padding: 8px 12px;
            background: #0f172a;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            color: #ffffff;
            font-size: 14px;
        }

        .cmd-btn {
            background: #10b981;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        .cmd-btn:hover {
            background: #059669;
        }
    </style>
</head>
<body>

    <!-- Desktop Workspace -->
    <div class="desktop">

        <!-- 1. Goat Farm App Shortcut (app.php) -->
        <a href="app.php" class="desktop-icon">
            <!-- Customized Goat/Farm SVG Icon -->
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 10C19 6.13401 15.866 3 12 3C8.13401 3 5 6.13401 5 10C5 12.3807 6.18562 14.4842 8 15.7294V19C8 19.5523 8.44772 20 9 20H15C15.5523 20 16 19.5523 16 19V15.7294C17.8144 14.4842 19 12.3807 19 10Z" fill="#10B981"/>
                <path d="M12 11C11.4477 11 11 11.4477 11 12V14C11 14.5523 11.4477 15 12 15C12.5523 15 13 14.5523 13 14V12C13 11.4477 12.5523 11 12 11Z" fill="#FFFFFF"/>
                <path d="M7 6C6.44772 6 6 6.44772 6 7C6 7.55228 6.44772 8 7 8C7.55228 8 8 7.55228 8 7C8 6.44772 7.55228 6 7 6Z" fill="#FBBF24"/>
                <path d="M17 6C16.4477 6 16 6.44772 16 7C16 7.55228 16.4477 8 17 8C17.5523 8 18 7.55228 18 7C18 6.44772 17.5523 6 17 6Z" fill="#FBBF24"/>
            </svg>
            <span>Goat Management</span>
        </a>

        <!-- 2. File Manager (pcmanfm) -->
        <form method="POST" class="desktop-icon-form" style="display:inline;">
            <input type="hidden" name="action" value="filemanager">
            <button type="submit" class="desktop-icon">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 6H12L10 4H4C2.89 4 2.01 4.89 2.01 6L2 18C2 19.1 2.89 20 4 20H20C21.1 20 22 19.1 22 18V8C22 6.9 21.1 6 20 6Z" fill="#F59E0B"/>
                </svg>
                <span>File Manager</span>
            </button>
        </form>

        <!-- 3. Terminal (lxterminal) -->
        <form method="POST" class="desktop-icon-form" style="display:inline;">
            <input type="hidden" name="action" value="terminal">
            <button type="submit" class="desktop-icon">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 4H4C2.89 4 2 4.9 2 6V18C2 19.1 2.89 20 4 20H20C21.1 20 22 19.1 22 18V6C22 4.9 21.1 4 20 4ZM20 18H4V8H20V18ZM18 12H12V14H18V12ZM10 10H6V16H10V10Z" fill="#4B5563"/>
                </svg>
                <span>Terminal</span>
            </button>
        </form>

        <!-- 4. Execute Command (Local Console Modal Toggle) -->
        <button type="button" class="desktop-icon" onclick="toggleModal()">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="24" height="24" rx="4" fill="#3B82F6"/>
                <path d="M7 10L10 12L7 14" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M11 14H16" stroke="white" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span>Run Command</span>
        </button>

        <!-- 5. Reboot -->
        <form method="POST" class="desktop-icon-form" style="display:inline;" onsubmit="return confirm('Are you sure you want to reboot the system?');">
            <input type="hidden" name="action" value="reboot">
            <button type="submit" class="desktop-icon">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 4V1L8 5L12 9V6C15.31 6 18 8.69 18 12C18 13.49 17.45 14.85 16.55 15.9L17.96 17.31C19.23 15.82 20 13.9 20 12C20 7.58 16.42 4 12 4ZM6 12C6 10.51 6.55 9.15 7.45 8.1L6.04 6.69C4.77 8.18 4 10.1 4 12C4 16.42 7.58 20 12 20V23L16 19L12 15V18C8.69 18 6 15.31 6 12Z" fill="#EF4444"/>
                </svg>
                <span>Reboot</span>
            </button>
        </form>

        <!-- 6. Shutdown -->
        <form method="POST" class="desktop-icon-form" style="display:inline;" onsubmit="return confirm('Are you sure you want to power off the system?');">
            <input type="hidden" name="action" value="shutdown">
            <button type="submit" class="desktop-icon">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M13 3H11V13H13V3ZM17.83 5.17L16.41 6.59C18.62 8.44 20 11.05 20 14C20 18.42 16.42 22 12 22C7.58 22 4 18.42 4 14C4 11.05 5.38 8.44 7.59 6.58L6.17 5.17C3.53 7.39 2 10.55 2 14C2 19.5 6.5 24 12 24C17.5 24 22 19.5 22 14C22 10.55 20.47 7.39 17.83 5.17Z" fill="#DC2626"/>
                </svg>
                <span>Power Off</span>
            </button>
        </form>

    </div>

    <!-- Floating System Console Modal -->
    <div class="modal <?php echo $show_modal ? 'active' : ''; ?>" id="cmdModal">
        <div class="modal-header">
            <div class="modal-title">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="24" height="24" rx="4" fill="#10B981"/>
                    <path d="M8 10L11 12L8 14" stroke="white" stroke-width="2"/>
                </svg>
                System Console (Command Panel)
            </div>
            <button type="button" class="modal-close" onclick="toggleModal()"></button>
        </div>
        <div class="modal-body">
            <textarea class="console-output" readonly placeholder="Command output will be displayed here..."><?php echo htmlspecialchars($output); ?></textarea>
            <form method="POST" class="cmd-form" target="_self">
                <input type="text" name="custom_command" class="cmd-input" placeholder="Type a command... (e.g., ls, df -h, free -m)" autofocus>
                <button type="submit" class="cmd-btn">Execute</button>
            </form>
        </div>
    </div>

    <!-- Taskbar -->
    <div class="taskbar">
        <div class="taskbar-left">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <circle cx="12" cy="12" r="10" fill="#10B981"/>
                <path d="M12 6C9.79 6 8 7.79 8 10C8 12.21 9.79 14 12 14C14.21 14 16 12.21 16 10C16 7.79 14.21 6 12 6ZM12 12C10.9 12 10 11.1 10 10C10 8.9 10.9 8 12 8C13.1 8 14 8.9 14 10C14 11.1 13.1 12 12 12Z" fill="white"/>
            </svg>
            <span>Goat OS (Smart Farm v1.0)</span>
        </div>
        <div class="taskbar-right">
            <span id="system-date"></span>
            <span id="system-time" style="color: #10B981;"></span>
        </div>
    </div>

    <script>
        // Realtime Clock Functionality
        function updateClock() {
            const now = new Date();
            
            // Format Date
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            const dateStr = now.toLocaleDateString('en-US', options);
            
            // Format Time
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // 0 should be 12
            minutes = minutes < 10 ? '0'+minutes : minutes;
            seconds = seconds < 10 ? '0'+seconds : seconds;
            
            const timeStr = `${hours}:${minutes}:${seconds} ${ampm}`;

            document.getElementById('system-date').innerText = dateStr;
            document.getElementById('system-time').innerText = timeStr;
        }

        // Run clock
        setInterval(updateClock, 1000);
        updateClock();

        // Toggle command execution modal
        function toggleModal() {
            const modal = document.getElementById('cmdModal');
            modal.classList.toggle('active');
        }
    </script>
</body>
</html>