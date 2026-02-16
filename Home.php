<?php
 session_start();
 include("db_connect.php"); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFP - Trip Ticket System (Appendix A)</title>
    <style>
        :root {
            --bfp-red: #B22222;
            --dark-blue: #2c3e50;
            --submenu-bg: #1a252f;
            --paper-shadow: rgba(0, 0, 0, 0.5);
        }

        /* ===== BASE LAYOUT ===== */
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #e9ecef;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        /* ===== SIDEBAR NAVIGATION ===== */
        .wrapper { display: flex; flex: 1; overflow: hidden; }

        main { 
            display: flex; 
            flex: 1; 
            padding: 20px; 
            gap: 20px; 
            overflow: hidden; 
        }

        footer { display: none; }
    </style>
</head>
<body>
<div class="wrapper">
    <?php include("Components/sidebar.php");?>

    <main>
        Blank main
    </main>
</div>

<script>
    document.querySelectorAll('.dropdown').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.closest('.submenu')) return;
            this.classList.toggle('active');
            document.querySelectorAll('.dropdown').forEach(other => {
                if (other !== this) other.classList.remove('active');
            });
        });
    });
</script>
</body>
</html>