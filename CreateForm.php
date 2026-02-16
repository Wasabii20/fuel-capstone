<?php
 session_start();
 include("db_connect.php"); 

// Determine current user's role and driver_id (if any)
$current_role = $_SESSION['role'] ?? '';
$current_user_id = $_SESSION['user_id'] ?? null;
$current_driver_id = null;
if ($current_user_id) {
    try {
        $u = $pdo->prepare("SELECT driver_id FROM users WHERE id = ? LIMIT 1");
        $u->execute([$current_user_id]);
        $urow = $u->fetch();
        $current_driver_id = $urow['driver_id'] ?? null;
    } catch (Exception $e) {
        $current_driver_id = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFP - Trip Ticket System (Appendix A)</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <style>
        :root {
            --bfp-red: #ff4757;
            --sidebar-bg: #1e1e2d;
            --card-bg: #252c3c;
            --text-primary: #ffffff;
            --text-secondary: #9899ac;
            --text-tertiary: #7e8299;
            --border-color: rgba(255, 255, 255, 0.05);
            --transition-speed: 0.3s;
            --paper-shadow: rgba(0, 0, 0, 0.5);
        }

        /* ===== BASE LAYOUT ===== */
        body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background-color: var(--sidebar-bg); display: flex; flex-direction: column; height: 100vh; color: var(--text-primary); overflow-x: hidden; }

        /* ===== HEADER ===== */
        header { display: none; }

        .wrapper { display: flex; flex: 1; overflow: hidden; }
        main { display: flex; flex: 1; padding: 30px; overflow: hidden; -webkit-overflow-scrolling: touch; }

        /* ===== 40/60 PANEL TOGGLE LOGIC ===== */
        .form-container { display: flex; width: 100%; gap: 20px; transition: all 0.5s ease; overflow: hidden; }
        
        .form-panel { flex: 1 1 100%; max-width: 100%; background: var(--card-bg); padding: 25px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3); overflow-y: auto; transition: all 0.5s ease; }
        .preview-panel { flex: 0 0 0%; max-width: 0%; opacity: 0; overflow: hidden; pointer-events: none; transition: all 0.5s ease; display: flex; justify-content: center; align-items: flex-start; }

        /* Scrollbar Styling */
        .form-panel::-webkit-scrollbar,
        .preview-panel::-webkit-scrollbar,
        .map-sidebar::-webkit-scrollbar,
        .locations-list::-webkit-scrollbar {
            width: 8px;
        }

        .form-panel::-webkit-scrollbar-track,
        .preview-panel::-webkit-scrollbar-track,
        .map-sidebar::-webkit-scrollbar-track,
        .locations-list::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .form-panel::-webkit-scrollbar-thumb,
        .preview-panel::-webkit-scrollbar-thumb,
        .map-sidebar::-webkit-scrollbar-thumb,
        .locations-list::-webkit-scrollbar-thumb {
            background: #5d5dff;
            border-radius: 10px;
            border: 2px solid rgba(0, 0, 0, 0.1);
        }

        .form-panel::-webkit-scrollbar-thumb:hover,
        .preview-panel::-webkit-scrollbar-thumb:hover,
        .map-sidebar::-webkit-scrollbar-thumb:hover,
        .locations-list::-webkit-scrollbar-thumb:hover {
            background: #4d4dff;
            border-color: rgba(93, 93, 255, 0.2);
        }

        .form-container.preview-active .form-panel { flex: 0 0 40%; max-width: 40%; border-radius: 16px; }
        .form-container.preview-active .preview-panel { flex: 0 0 60%; max-width: 60%; opacity: 1; pointer-events: auto; padding-right: 0; padding-top: 20px; overflow-y: auto; }

        .btn-preview-toggle { background: #5d5dff; color: white; padding: 12px; width: 100%; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; margin-bottom: 20px; transition: all var(--transition-speed) ease; }
        .btn-preview-toggle:hover { background: #4d4dff; transform: translateY(-2px); }
        .btn-preview-toggle.active { background: #4d4dff; }

        /* Form Details */
        .form-section-title { background: rgba(0, 0, 0, 0.2); padding: 12px; border-left: 4px solid #5d5dff; margin: 20px 0 10px 0; font-weight: bold; font-size: 0.9rem; color: var(--text-secondary); border-radius: 4px; }
        label { display: block; font-size: 0.75rem; font-weight: bold; margin-bottom: 5px; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
        input, select { 
            width: 100%; 
            padding: 10px 12px; 
            margin-bottom: 10px; 
            border: 1px solid var(--border-color); 
            border-radius: 8px; 
            box-sizing: border-box; 
            background: rgba(0, 0, 0, 0.2); 
            color: var(--text-primary); 
            font-family: inherit; 
            transition: all var(--transition-speed) ease;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239899ac' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            padding-right: 40px;
        }
        
        select option {
            background: var(--card-bg);
            color: var(--text-primary);
        }
        
        input:hover, select:hover { border-color: rgba(93, 93, 255, 0.3); }
        input:focus, select:focus { outline: none; border-color: #5d5dff; background: rgba(93, 93, 255, 0.08); }
        .btn-submit { background: #5d5dff; color: white; padding: 15px; width: 100%; border: none; border-radius: 12px; font-weight: bold; cursor: pointer; font-size: 1rem; margin-top: 10px; transition: all var(--transition-speed) ease; }
        .btn-submit:hover { background: #4d4dff; transform: translateY(-2px); box-shadow: 0 5px 20px rgba(93, 93, 255, 0.3); }

        /* ===== LOCATION PICKER STYLES ===== */
        .location-input-wrapper {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        #in_places {
            flex: 1;
        }


        .btn-map-picker {
            background: #5d5dff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all var(--transition-speed) ease;
            height: 42px;
        }

        .btn-map-picker:hover {
            background: #4d4dff;
            transform: translateY(-2px);
        }

        /* Map Modal Styles */
        .map-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .map-modal-content {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            margin: 5% auto;
            padding: 20px;
            border-radius: 16px;
            width: 90%;
            max-width: 900px;
            height: 600px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .map-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .map-modal-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.3rem;
        }

        .close-map-modal {
            font-size: 28px;
            font-weight: bold;
            color: var(--text-tertiary);
            cursor: pointer;
            transition: color var(--transition-speed) ease;
            background: none;
            border: none;
        }

        .close-map-modal:hover {
            color: var(--bfp-red);
        }

        .map-modal-body {
            display: flex;
            gap: 15px;
            flex: 1;
            overflow: hidden;
        }

        #locationMap {
            flex: 1;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .map-sidebar {
            width: 250px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            overflow-y: auto;
        }

        .map-search-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.2);
            color: var(--text-primary);
            font-size: 14px;
            margin-bottom: 10px;
            transition: all var(--transition-speed) ease;
        }

        .map-search-input:focus {
            outline: none;
            border-color: #5d5dff;
            background: rgba(93, 93, 255, 0.08);
        }

        /* Locations Tab Styles */
        .locations-tab {
            margin-top: 10px;
            border-top: 1px solid var(--border-color);
            padding-top: 10px;
        }

        .locations-tab-header {
            font-weight: 700;
            color: var(--text-secondary);
            font-size: 12px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .locations-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-height: 350px;
            overflow-y: auto;
        }

        .location-card {
            padding: 10px 12px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 12px;
        }

        .location-card:hover {
            background: rgba(93, 93, 255, 0.15);
            border-color: #5d5dff;
            box-shadow: 0 2px 8px rgba(93, 93, 255, 0.2);
            transform: translateX(2px);
        }

        .location-card-name {
            font-weight: 600;
            color: #5d5dff;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .location-card-category {
            font-size: 10px;
            color: var(--text-tertiary);
            background: rgba(93, 93, 255, 0.15);
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .location-suggestion {
            padding: 10px 12px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .location-suggestion:hover {
            background: #5d5dff;
            color: white;
            border-color: #5d5dff;
        }

        .selected-location {
            background: #5d5dff;
            color: white;
            border-color: #5d5dff;
        }

        .map-modal-footer {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: flex-end;
        }

        .btn-select-location {
            background: #5d5dff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all var(--transition-speed) ease;
        }

        .btn-select-location:hover {
            background: #4d4dff;
            transform: translateY(-2px);
        }

        .btn-cancel-map {
            background: rgba(166, 166, 190, 0.15);
            color: #a6a6be;
            padding: 10px 20px;
            border: 1px solid rgba(166, 166, 190, 0.3);
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all var(--transition-speed) ease;
        }

        .btn-cancel-map:hover {
            background: rgba(166, 166, 190, 0.25);
            border-color: #a6a6be;
        }

        .autocomplete-suggestions {
            position: absolute;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-top: none;
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 200px;
            overflow-y: auto;
            width: 100%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 100;
            border-radius: 0 0 8px 8px;
        }

        .autocomplete-suggestions li {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            transition: all 0.2s ease;
        }

        .autocomplete-suggestions li:hover {
            background: rgba(93, 93, 255, 0.15);
            color: #5d5dff;
            padding-left: 16px;
        }

        /* ===== ADD LOCATION MODAL STYLES ===== */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s ease;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            padding: 25px;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .modal-content h2 {
            margin-top: 0;
            color: var(--text-primary);
            font-size: 1.3rem;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.2);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 14px;
            transition: all var(--transition-speed) ease;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #5d5dff;
            background: rgba(93, 93, 255, 0.08);
        }

        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239899ac' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            padding-right: 40px;
        }

        .form-group select option {
            background: var(--card-bg);
            color: var(--text-primary);
        }

        .modal-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-save {
            background: #5d5dff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all var(--transition-speed) ease;
        }

        .btn-save:hover {
            background: #4d4dff;
            transform: translateY(-2px);
        }

        .btn-cancel {
            background: rgba(166, 166, 190, 0.15);
            color: #a6a6be;
            padding: 10px 20px;
            border: 1px solid rgba(166, 166, 190, 0.3);
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all var(--transition-speed) ease;
        }

        .btn-cancel:hover {
            background: rgba(166, 166, 190, 0.25);
            border-color: #a6a6be;
        }

        /* ===== OFFICIAL TICKET DOCUMENT STYLING ===== */
       .ticket-page {
    position: relative; 
    width: 8.5in; 
    height: 960px; 
    background: white; 
    padding: 0.5in;
    box-shadow: 0 0 15px var(--paper-shadow); 
    font-family: "Times New Roman", Times, serif;
    color: black; 
    font-size: 10.5pt; 
    line-height: 1.15;
    transform-origin: top center;
}

/* Auto-scale the ticket preview when window is too narrow */
@media (max-width: 900px) {
    .ticket-page {
        transform: scale(0.8);
    }
}

/* Official Seal Positioning */
.seal { 
    position: absolute;
    left: 0.6in;
    top: 0.5in;
    width: 85px; 
    height: 85px;
}

.doc-header { text-align: center; margin-bottom: 10px; line-height: 1.3; }
.fire-station-name { text-decoration: underline; font-weight: bold; }
.appendix { position: absolute; font-weight: bold; right: 0.5in; top: 0.4in; }
.control-block { text-align: right; margin-top: -10px; }

.underline { 
    border-bottom: 1px solid black; 
    display: inline-block; 
    min-width: 100px; 
    padding: 0 5px; 
    text-align: center;
}
.dotted { 
    border-bottom: 1px solid black; 
    flex: 1; 
    margin-left: 5px; 
    min-height: 1.1em; 
    padding-left: 5px;
}
.line-item { display: flex; align-items: flex-end; margin-bottom: 3px; }
.indent { margin-left: 25px; }

.gas-table { width: 100%; border-collapse: collapse; margin: 5px 0; }
.sig-box { 
    width: 250px; 
    border-top: 1px solid black; 
    text-align: center; 
    padding-top: 5px; 
    font-weight: bold; 
    margin: 25px auto 5px auto; 
}
.Sig-driver { 
    width: 250px; 
    margin: 20px auto 5px auto; 
    text-align: center; 
    text-decoration: none; 
    font-weight: bold; 
    text-underline-offset: 4px; 
}
.sig-driver {
    width: 250px;
    margin: 0;
    padding: 0 0 0 calc(50% - 125px);
    text-align: center;
    text-decoration: none;
    font-weight: bold;
    text-underline-offset: 4px;
    float: left;
    clear: both;
}
.sig-row {
    display: flex;
    justify-content: flex-end;
}
.sig-block {
    text-align: center;
}

/* ===== MOBILE RESPONSIVE STYLES ===== */
@media (max-width: 768px) {
    body {
        height: auto;
        min-height: 100vh;
    }

    .wrapper {
        flex-direction: column;
    }

    main {
        padding: 15px;
        flex: 1;
        overflow-y: auto;
    }

    .form-container {
        flex-direction: column;
        gap: 15px;
    }

    .form-panel {
        flex: 1 1 100%;
        max-width: 100%;
        padding: 20px;
        border-radius: 12px;
    }

    .preview-panel {
        flex: 0 0 0;
        max-width: 0;
        opacity: 0;
        pointer-events: none;
    }

    .form-container.preview-active .form-panel {
        flex: 0 0 auto;
        max-width: 100%;
        height: auto;
        padding: 15px 20px;
        max-height: none;
        overflow: visible;
    }

    .form-container.preview-active .form-panel form {
        display: none;
    }

    .form-container.preview-active .preview-panel {
        flex: 1 1 100%;
        max-width: 100%;
        opacity: 1;
        pointer-events: auto;
        padding: 15px;
        overflow-y: auto;
        position: relative;
    }

    .btn-preview-toggle {
        padding: 12px;
        font-size: 0.9rem;
        margin-bottom: 0;
        position: relative;
        z-index: 50;
        pointer-events: auto;
    }

    .form-section-title {
        padding: 10px;
        font-size: 0.85rem;
        margin: 15px 0 10px 0;
    }

    label {
        font-size: 0.7rem;
        margin-bottom: 4px;
    }

    input, select {
        padding: 10px;
        margin-bottom: 12px;
        font-size: 0.95rem;
    }

    .location-input-wrapper {
        flex-direction: column;
        gap: 8px;
    }

    .btn-map-picker {
        width: 100%;
        height: auto;
        padding: 10px;
    }

    .btn-submit {
        padding: 12px;
        font-size: 0.95rem;
    }

    /* Map Modal Mobile Styles */
    .map-modal-content {
        width: 95%;
        max-width: 100%;
        height: 90vh;
        max-height: 90vh;
        margin: 5% auto;
        border-radius: 12px;
        padding: 15px;
    }

    .map-modal-header {
        margin-bottom: 12px;
        padding-bottom: 12px;
    }

    .map-modal-header h2 {
        font-size: 1.1rem;
    }

    .map-modal-body {
        gap: 12px;
        flex-direction: column;
    }

    #locationMap {
        height: 250px;
    }

    .map-sidebar {
        width: 100%;
        max-height: 300px;
    }

    .map-search-input {
        padding: 8px 10px;
        font-size: 14px;
    }

    .locations-list {
        max-height: 200px;
    }

    .location-card {
        padding: 8px 10px;
        font-size: 0.8rem;
    }

    .map-modal-footer {
        flex-direction: column;
        gap: 8px;
    }

    .btn-select-location,
    .btn-cancel-map {
        width: 100%;
        padding: 10px;
    }

    /* Modal Mobile Styles */
    .modal-content {
        width: 95%;
        max-width: 100%;
        padding: 20px;
    }

    .modal-content h2 {
        font-size: 1.1rem;
        margin-bottom: 15px;
    }

    .form-group input,
    .form-group select {
        padding: 10px;
        font-size: 16px;
    }

    .modal-buttons {
        flex-direction: column-reverse;
        gap: 8px;
    }

    .btn-save,
    .btn-cancel {
        width: 100%;
        padding: 10px;
    }

    .ticket-page {
        width: 100%;
        height: auto;
        padding: 15px;
        transform: scale(1);
    }

    .header-section {
        flex-direction: row-reverse !important;
        justify-content: flex-start !important;
        align-items: center !important;
        padding-left: 0 !important;
        gap: 15px !important;
    }

    .seal {
        width: 60px;
        height: 60px;
        left: 0.3in;
    }

    .appendix {
        font-size: 0.9rem;
    }
}

@media (max-width: 480px) {
    main {
        padding: 10px;
    }

    .form-panel {
        padding: 15px;
        border-radius: 10px;
    }

    .btn-preview-toggle {
        padding: 10px;
        font-size: 0.85rem;
        margin-bottom: 12px;
    }

    .form-section-title {
        padding: 8px;
        font-size: 0.8rem;
        margin: 12px 0 8px 0;
    }

    label {
        font-size: 0.65rem;
        margin-bottom: 3px;
    }

    input, select {
        padding: 8px;
        margin-bottom: 10px;
        font-size: 0.9rem;
    }

    .location-input-wrapper {
        flex-direction: column;
    }

    .btn-map-picker {
        padding: 8px;
        font-size: 0.85rem;
    }

    .btn-submit {
        padding: 10px;
        font-size: 0.9rem;
        margin-top: 8px;
    }

    .map-modal-content {
        padding: 12px;
        height: 85vh;
        max-height: 85vh;
    }

    .map-modal-header {
        margin-bottom: 10px;
        padding-bottom: 10px;
    }

    .map-modal-header h2 {
        font-size: 1rem;
    }

    .close-map-modal {
        font-size: 24px;
    }

    #locationMap {
        height: 150px;
        border-radius: 6px;
    }

    .map-sidebar {
        max-height: 200px;
    }

    .map-search-input {
        padding: 8px;
        font-size: 13px;
        margin-bottom: 8px;
    }

    .locations-list {
        gap: 4px;
        max-height: 150px;
    }

    .location-card {
        padding: 6px 8px;
        font-size: 0.75rem;
    }

    .location-card-name {
        font-size: 0.8rem;
    }

    .location-card-category {
        font-size: 0.65rem;
        padding: 1px 4px;
    }

    .map-modal-footer {
        flex-direction: column-reverse;
        gap: 6px;
        margin-top: 10px;
    }

    .btn-select-location,
    .btn-cancel-map {
        width: 100%;
        padding: 8px 12px;
        font-size: 0.85rem;
    }

    .modal-content {
        width: 95%;
        padding: 15px;
        border-radius: 10px;
    }

    .modal-content h2 {
        font-size: 0.95rem;
        margin-bottom: 12px;
    }

    .form-group {
        margin-bottom: 12px;
    }

    .form-group label {
        font-size: 0.65rem;
        margin-bottom: 3px;
    }

    .form-group input,
    .form-group select {
        padding: 8px;
        font-size: 14px;
    }

    .modal-buttons {
        flex-direction: column-reverse;
        gap: 6px;
        margin-top: 15px;
    }

    .btn-save,
    .btn-cancel {
        width: 100%;
        padding: 8px;
        font-size: 0.85rem;
    }

    .ticket-page {
        padding: 10px;
        font-size: 9pt;
        height: auto;
    }

    .header-section {
        flex-direction: row-reverse !important;
        justify-content: flex-start !important;
        align-items: center !important;
        padding-left: 0 !important;
        gap: 10px !important;
        margin-bottom: 15px !important;
    }

    .seal {
        width: 50px;
        height: 50px;
        left: 0.25in;
        top: 0.3in;
    }

    .appendix {
        font-size: 0.8rem;
        right: 0.25in;
        top: 0.25in;
    }

    .sig-row {
        flex-direction: column;
    }

    .sig-driver,
    .Sig-driver {
        width: 100%;
        padding: 0;
        margin: 20px 0 5px 0;
    }
}
    </style>
</head>
<body>

<div class="wrapper">
    <?php include("Components/sidebar.php");?>

    <main>
        <div class="form-container" id="mainContainer">
            <section class="form-panel">
                <button type="button" id="previewToggleBtn" class="btn-preview-toggle">📋 Show Preview</button>
                
                <form action="submit.php" method="post">
                    <?php if ($current_role === 'admin'): ?>
                        <div class="form-section-title">A. ADMINISTRATIVE SECTION</div>
                        <label>Control No.</label>
                        <input type="text" id="in_control" name="control_no" oninput="update('p_control', this.value)">
                        <label>Date</label>
                        <input type="date" id="in_date" name="date" oninput="update('p_date', this.value)">
                        <label>Driver Name</label>
                        <select id="in_driver" name="driver_id" onchange="updateDriver(this.value)">
                            <option value="">-- Select Driver --</option>
                            <?php
                                $query = "SELECT driver_id, full_name FROM drivers WHERE status = 'active' ORDER BY full_name ASC";
                                $result = $pdo->query($query);
                                while($row = $result->fetch()) {
                                    echo '<option value="'.htmlspecialchars($row['driver_id']).'">'.htmlspecialchars($row['full_name']).'</option>';
                                }
                            ?>
                        </select>
                    <?php endif; ?>

                    <label>Plate No.</label>
                    <select id="in_plate" name="vehicle_no" onchange="updateVehicleFuel(this.value)">
                        <option value="">-- Select Vehicle --</option>
                        <?php
                            $vehicle_query = "SELECT vehicle_no, current_fuel FROM vehicles WHERE status IN ('available', 'deployed') ORDER BY vehicle_no ASC";
                            $vehicle_result = $pdo->query($vehicle_query);
                            while($vrow = $vehicle_result->fetch()) {
                                echo '<option value="'.htmlspecialchars($vrow['vehicle_no']).'" data-fuel="'.htmlspecialchars($vrow['current_fuel']).'">'.htmlspecialchars($vrow['vehicle_no']).'</option>';
                            }
                        ?>
                    </select>
                    <label>Authorized Passenger</label>
                    <input type="text" id="in_pass" name="Autho_passenger" oninput="update('p_pass', this.value)">
                    <label>Places to Visit</label>
                    <div class="location-input-wrapper">
                        <input type="text" id="in_places" name="places_to_visit" placeholder="Type location or press Map button" oninput="update('p_places', this.value)">
                        <button type="button" id="mapPickerBtn" class="btn-map-picker" title="Open map to select location">📍 Map</button>
                    </div>
                    <div id="selectedLocationDisplay" style="display: none; padding: 12px; background: rgba(93, 93, 255, 0.08); border: 1px solid rgba(93, 93, 255, 0.3); border-radius: 8px; margin-top: 8px; font-size: 0.85rem;">
                        <div style="color: #5d5dff; font-weight: 600; margin-bottom: 6px;">✓ Selected Location</div>
                        <div style="color: #9899ac; margin-bottom: 4px;"><strong id="selectedLocName"></strong></div>
                        <div style="color: #7e8299; font-size: 0.8rem;">
                            <span style="display: inline-block; margin-right: 12px;">📍 Lat: <strong id="selectedLocLat">--</strong></span>
                            <span style="display: inline-block;">Lng: <strong id="selectedLocLng">--</strong></span>
                        </div>
                        <div style="color: #7e8299; font-size: 0.8rem; margin-top: 6px;">
                            📏 Distance: <strong id="selectedLocDist">--</strong> km (round trip)
                        </div>
                    </div>

                    <label>Purpose</label>
                    <input type="text" id="in_purpose" name="purpose" oninput="update('p_purpose', this.value)">

                    <div class="form-section-title">B. DRIVER SECTION</div>
                    <label>Time Departed (Garage)</label>
                    <input type="text" id="in_dep1" name="dep_office_time" oninput="update('p_dep1', this.value)">
                    <label>Time Arrival at Destination</label>
                    <input type="text" id="in_arr_dest" name="arr_location_time" oninput="update('p_arrival_at', this.value)">
                    <label>Time Departure from Destination</label>
                    <input type="text" id="in_dep_dest" name="dep_location_time" oninput="update('p_dep_from', this.value)">
                    <label>Time Arrival back at Garage</label>
                    <input type="text" id="in_arr_back" name="arr_office_time" oninput="update('p_arrival_back', this.value)">

                    <label>Approx. Distance (kms)</label>
                    <input type="number" id="in_dist" name="approx_distance" step="0.01" min="0" oninput="update('p_dist', this.value); update('p_speed_dist', this.value)">
                    
                    <label>Balance in Tank</label>
                    <input type="number" id="in_bal" name="gas_balance_start" oninput="calculateGas()">
                    <label>Issued from Stock</label>
                    <input type="number" id="in_issued" name="gas_issued_office" oninput="calculateGas()">
                    <label>Add Purchased during trip</label>
                    <input type="number" id="in_purchased" name="gas_added_trip" oninput="calculateGas()">
                    <label>Deduct Used during trip</label>
                    <input type="number" id="in_used" name="gas_deducted" oninput="calculateGas()">

                    <label>Gear Oil Issued</label>
                    <input type="number" id="in_gear_oil" name="gear_oil" oninput="update('p_gear_oil', this.value)">
                    <label>Lub. Oil Issued</label>
                    <input type="number" id="in_lub_oil" name="lub_oil" oninput="update('p_lub_oil', this.value)">
                    <label>Grease Issued</label>
                    <input type="number" id="in_grease" name="grease" oninput="update('p_grease', this.value)">
                    <label>Speedometer at Beginning of Trip</label>
                    <input type="number" id="in_speed_begin" name="speedometer_start" oninput="update('p_speed_begin', this.value)">
                    <label>Speedometer at End of Trip</label>
                    <input type="number" id="in_speed_end" name="speedometer_end" oninput="update('p_speed_end', this.value)">
                    <label>Remarks</label>
                    <input type="text" id="in_remarks" name="remarks" oninput="update('p_remarks', this.value)">

                    <div style="margin-top:30px; padding-top:20px; border-top: 2px solid #5d5dff; font-weight:bold;">PASSENGER CERTIFICATION SECTION</div>
                    <div style="display: flex; justify-content: space-between; gap: 20px; margin-top: 20px;">
                        <div style="flex: 1; text-align: center;">
                            <div style="border-bottom: 1px solid #000; padding: 20px 0; margin-bottom: 10px;">
                                <input type="text" id="in_pass1" name="passenger_1_name" placeholder="Name" style="width: 100%; border: none; text-align: center; background: transparent; font-size: 12px;" oninput="update('p_pass1', this.value); updatePassengerDisplay(1)">
                            </div>
                            <input type="date" id="in_date1" name="passenger_1_date" style="width: 100%; border: none; text-align: center; background: transparent; font-size: 11px; margin-top: 5px;" oninput="updatePassengerDisplay(1)">
                        </div>
                        <div style="flex: 1; text-align: center;">
                            <div style="border-bottom: 1px solid #000; padding: 20px 0; margin-bottom: 10px;">
                                <input type="text" id="in_pass2" name="passenger_2_name" placeholder="Name" style="width: 100%; border: none; text-align: center; background: transparent; font-size: 12px;" oninput="update('p_pass2', this.value); updatePassengerDisplay(2)">
                            </div>
                            <input type="date" id="in_date2" name="passenger_2_date" style="width: 100%; border: none; text-align: center; background: transparent; font-size: 11px; margin-top: 5px;" oninput="updatePassengerDisplay(2)">
                        </div>
                        <div style="flex: 1; text-align: center;">
                            <div style="border-bottom: 1px solid #000; padding: 20px 0; margin-bottom: 10px;">
                                <input type="text" id="in_pass3" name="passenger_3_name" placeholder="Name" style="width: 100%; border: none; text-align: center; background: transparent; font-size: 12px;" oninput="update('p_pass3', this.value); updatePassengerDisplay(3)">
                            </div>
                            <input type="date" id="in_date3" name="passenger_3_date" style="width: 100%; border: none; text-align: center; background: transparent; font-size: 11px; margin-top: 5px;" oninput="updatePassengerDisplay(3)">
                        </div>
                    </div>

                    <!-- Status Field - Hidden, defaults to Pending -->
                    <input type="hidden" name="status" value="Pending">
                    
                    <!-- Hidden fields for gas calculations -->
                    <input type="hidden" id="in_gas_total" name="gas_total" value="0">
                    <input type="hidden" id="in_gas_balance_end" name="gas_balance_end" value="0">

                    <input type="submit" value="Submit to Database" class="btn-submit">
                </form>
            </section>

            <section class="preview-panel" id="previewPanel">
                <div class="ticket-page">
                    <div class="appendix">Appendix A</div>
                        <div class="header-section" style="display: flex; align-items: center; justify-content: flex-start; gap: 15px; margin-bottom: 20px; padding-left: 130px;">
                            
                            <img src="ALBUM/Official Seal (2).png" alt="Logo" 
                                style="width: 80px; height: 80px; flex-shrink: 0; margin-top: -15px;">
                            
                            <div style="text-align: center; line-height: 1.3;">
                                <div style="font-size: 10.5pt;">Republic of the Philippines</div>
                                <div style="font-size: 10.5pt;">Province of Southern Leyte</div>
                                <strong style="font-size: 14pt; display: block; margin: 2px 0;">CITY OF MAASIN</strong>
                                <div style="font-size: 10.5pt;">
                                    Office of the <span class="fire-station-name" style="text-decoration: underline; font-weight: bold;">MAASIN CITY FIRE STATION</span>
                                </div>
                            </div>
                        </div>

                    <div class="control-block">
                        Control No: <span class="underline" style="min-width: 150px;" id="p_control"></span></div>

                            <h3 style="text-align: center; margin: 15px 0 0 0;">DRIVER'S TRIP TICKET</h3>
                            <div style="text-align: center; margin-bottom: 15px;">(<span id="p_date">Date</span>)</div>

                            <p><strong>A. To be filled by the Administrative Official Authorizing Official Travel:</strong></p>
                            <div class="indent">
                                <div class="line-item">1. Name of Driver of the Vehicle: <span class="dotted" id="p_driver"></span></div>
                                <div class="line-item">2. Government car to be used. Plate No.: <span class="dotted" id="p_plate"></span></div>
                                <div class="line-item">3. Name of Authorized Passenger: <span class="dotted" id="p_pass"></span></div>
                                <div class="line-item">4. Place or places to be visited/inspected: <span class="dotted" id="p_places"></span></div>
                                <div class="line-item">5. Purpose: <span class="dotted" id="p_purpose"></span></div><br>
                            </div>

                            <div class="sig-row">
                                <div class="sig-block">
                                    <span class="underline" style="width: 200px;"></span><br>
                                    <small>Head of Office or his duly<br>Authorized Representative</small>
                                </div>
                            </div>

                            <p><strong>B. To be filled by the Driver:</strong></p>
                            <div class="indent">
                                <div class="line-item">1. Time of departure from Office / Garage: <span class="dotted" id="p_dep1"></span> a.m./p.m.</div>
                                <div class="line-item">2. Time of arrival at (per No. 4 above): <span class="dotted" id="p_arrival_at"></span> a.m./p.m.</div>
                                <div class="line-item">3. Time of departure from (per No. 4): <span class="dotted" id="p_dep_from"></span> a.m./p.m.</div>
                                <div class="line-item">4. Time of arrival back to Office/Garage: <span class="dotted" id="p_arrival_back"></span> a.m./p.m.</div>
                                <div class="line-item">5. Approximate distance travelled (to and from): <span class="dotted" id="p_dist"></span> kms.</div>
                                <div class="line-item">6. Gasoline issued, purchase and consumed:</div>
                                <div class="indent">
                                    <table class="gas-table">
                                        <tr><td style="width: 280px;">a. Balance in Tank:</td><td class="dotted" id="p_bal"></td><td style="width: 50px; padding-left: 5px;">liters</td></tr>
                                        <tr><td>b. Issued by Office from Stock:</td><td class="dotted" id="p_issued"></td><td>liters</td></tr>
                                        <tr><td>c. Add purchased during trip:</td><td class="dotted" id="p_purchased"></td><td>liters</td></tr>
                                        <tr><td style="padding-left: 40px;"><strong>TOTAL. . . :</strong></td><td class="dotted" id="p_total"></td><td><strong>liters</strong></td></tr>
                                        <tr><td>d. Deduct Used during the trip (to and from):</td><td class="dotted" id="p_used"></td><td>liters</td></tr>
                                        <tr><td>e. Balance in tank at the end of trip:</td><td class="dotted" id="p_end"></td><td>liters</td></tr>
                                    </table>
                                </div>
                                <div class="line-item">7. Gear oil issued: <span class="dotted" id="p_gear_oil"></span> liters</div>
                                <div class="line-item">8. Lub. Oil issued: <span class="dotted" id="p_lub_oil"></span> liters</div>
                                <div class="line-item">9. Grease issued: <span class="dotted" id="p_grease"></span> liters</div>
                                <div class="line-item">10. Speedometer readings, if any:</div>
                                <div class="indent" style="margin-top: 2px;">
                                    <div class="line-item">&nbsp;&nbsp;&nbsp;&nbsp;At beginning of trip <span class="dotted" id="p_speed_begin"></span> kms.</div>
                                    <div class="line-item">&nbsp;&nbsp;&nbsp;&nbsp;At end of trip <span class="dotted" id="p_speed_end"></span> kms.</div>
                                    <div class="line-item">&nbsp;&nbsp;&nbsp;&nbsp;Distance travelled (per No. 5 above) <span class="dotted" id="p_speed_dist"></span> kms.</div>
                                </div>
                                <div class="line-item">11. Remarks: <span class="dotted" id="p_remarks"></span></div>
                            </div>

                            <div style="margin-top: 20px; text-align: center; font-style: italic;">
                                I hereby certify to the correctness of the above statement of record of travel.
                            </div>
                            <div class="sig-driver" id="p_driver_sig"></div>
                            <div class="sig-box" style="width: 250px; margin-top: 20px;">Driver</div>
                            
                            <div style="margin-top: 15px; text-align: center;">
                                I hereby certify that I used this car on official business as stated above.
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; gap: 20px; margin-top: 25px; font-size: 9pt;">
                                <div style="flex: 1; text-align: center;">
                                    <div id="p_pass1" style="border-bottom: 1px solid #000; padding: 20px 0; margin-bottom: 10px; min-height: 20px;"></div>
                                    <small>Name of Passenger / Date</small>
                                </div>
                                <div style="flex: 1; text-align: center;">
                                    <div id="p_pass2" style="border-bottom: 1px solid #000; padding: 20px 0; margin-bottom: 10px; min-height: 20px;"></div>
                                    <small>Name of Passenger / Date</small>
                                </div>
                                <div style="flex: 1; text-align: center;">
                                    <div id="p_pass3" style="border-bottom: 1px solid #000; padding: 20px 0; margin-bottom: 10px; min-height: 20px;"></div>
                                    <small>Name of Passenger / Date</small>
                                </div>
                            </div>
                        </div>
                        
            </section>
        </div>
    </main>
</div>

<!-- Location Picker Modal -->
<div id="mapModal" class="map-modal">
    <div class="map-modal-content">
        <div class="map-modal-header">
            <h2>📍 Select Location on Map</h2>
            <span class="close-map-modal">&times;</span>
        </div>
        <div class="map-modal-body">
            <div id="locationMap"></div>
            <div class="map-sidebar">
                <input type="text" id="mapSearchInput" class="map-search-input" placeholder="Search location...">
                <div id="suggestionsList"></div>
                
                <!-- Selected Coordinates Display -->
                <div id="coordinatesDisplay" style="padding: 12px; background: rgba(93, 93, 255, 0.1); border-radius: 6px; margin: 10px 0; display: none; border: 1px solid rgba(93, 93, 255, 0.3);">
                    <div style="font-size: 0.75rem; font-weight: bold; color: #5d5dff; margin-bottom: 8px; text-transform: uppercase;">📍 Selected Coordinates</div>
                    <div id="selectedCoordLat" style="font-size: 0.85rem; color: #9899ac; margin-bottom: 4px;">Latitude: <strong>--</strong></div>
                    <div id="selectedCoordLng" style="font-size: 0.85rem; color: #9899ac; margin-bottom: 10px;">Longitude: <strong>--</strong></div>
                    <button type="button" id="addNewLocationBtn" onclick="openAddLocationModal()" style="width: 100%; padding: 10px; background: #B22222; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: all 0.3s;" onmouseover="this.style.background='#9a1a1a'" onmouseout="this.style.background='#B22222'">
                        + Add New Location
                    </button>
                </div>
                
                <!-- Locations Tab -->
                <div class="locations-tab">
                    <div class="locations-tab-header">📍 Available Locations</div>
                    <div class="locations-list" id="locationsList">
                        <div style="text-align: center; color: #999; padding: 20px 10px; font-size: 12px;">Loading locations...</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="map-modal-footer">
            <button type="button" id="selectLocationBtn" class="btn-select-location">✓ Select Location</button>
            <button type="button" id="cancelMapBtn" class="btn-cancel-map">Cancel</button>
        </div>
    </div>
</div>

<!-- Location Detail Modal (for naming) -->
<div id="locationDetailModal" class="map-modal" style="display: none;">
    <div class="map-modal-content" style="max-width: 400px; height: auto;">
        <div class="map-modal-header">
            <h2>💾 Save Location Name</h2>
            <span class="close-detail-modal" style="cursor: pointer;">&times;</span>
        </div>
        <form id="locationDetailForm" style="padding: 20px;">
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px;">Location Name *</label>
                <input type="text" id="locationNameInput" placeholder="e.g., Maasin City Park" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="display: block; font-weight: bold; margin-bottom: 5px; font-size: 12px;">Coordinates</label>
                <input type="text" id="locationCoordsDisplay" readonly style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; background: #f5f5f5; color: #666;">
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="submit" class="btn-select-location">✓ Save Location</button>
                <button type="button" class="btn-cancel-map" id="cancelDetailBtn">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Location Modal (from Maps.php) -->
<div class="modal" id="addLocationModal">
    <div class="modal-content">
        <h2>Add New Location</h2>
        <form id="addLocationForm">
            <div class="form-group">
                <label>Location Name *</label>
                <input type="text" id="locationName" required>
            </div>
            <div class="form-group">
                <label>Category *</label>
                <select id="locationCategory" required onchange="toggleCustomCategory()">
                    <option value="">Select Category</option>
                    <option value="Fire Station">Fire Station</option>
                    <option value="Park">Park</option>
                    <option value="School">School</option>
                    <option value="Hospital">Hospital</option>
                    <option value="Shopping">Shopping</option>
                    <option value="Religious">Religious</option>
                    <option value="Sports">Sports</option>
                    <option value="Terminal">Terminal</option>
                    <option value="Government">Government</option>
                    <option value="Port">Port</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div class="form-group" id="customCategoryGroup" style="display: none;">
                <label>Custom Category *</label>
                <input type="text" id="customCategory" placeholder="Enter custom category" maxlength="50">
            </div>
            <div class="form-group">
                <label>Latitude *</label>
                <input type="number" id="locationLat" step="0.000001" required>
            </div>
            <div class="form-group">
                <label>Longitude *</label>
                <input type="number" id="locationLng" step="0.000001" required>
            </div>
            <div class="modal-buttons">
                <button type="submit" class="btn-save">💾 Save Location</button>
                <button type="button" class="btn-cancel" onclick="closeAddLocationModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    // --- sidebar dropdown Toggling ---
    document.querySelectorAll('.dropdown').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.closest('.submenu')) return;
            this.classList.toggle('active');
            document.querySelectorAll('.dropdown').forEach(other => {
                if (other !== this) other.classList.remove('active');
            });
        });
    });
    // --- Panel Toggling ---
    document.addEventListener('DOMContentLoaded', () => {
        const toggleBtn = document.getElementById('previewToggleBtn');
        const container = document.getElementById('mainContainer');
        
        if(toggleBtn && container) {
            toggleBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const isActive = container.classList.toggle('preview-active');
                toggleBtn.classList.toggle('active');
                toggleBtn.textContent = isActive ? '📋 Hide Preview' : '📋 Show Preview';
            });
        }
    });

    // ===== ADD NEW LOCATION MODAL FUNCTIONS =====
    let currentMapCoords = null; // Store the current selected coordinates from map click
    
    function openAddLocationModal() {
        if (!currentMapCoords) {
            alert('Please select a location on the map first by clicking on it');
            return;
        }
        
        // Pre-fill the modal with current map coordinates
        document.getElementById('locationName').value = '';
        document.getElementById('locationCategory').value = '';
        document.getElementById('locationLat').value = currentMapCoords.lat.toFixed(6);
        document.getElementById('locationLng').value = currentMapCoords.lng.toFixed(6);
        document.getElementById('addLocationModal').classList.add('active');
        document.getElementById('locationName').focus();
    }

    function closeAddLocationModal() {
        document.getElementById('addLocationModal').classList.remove('active');
        document.getElementById('addLocationForm').reset();
        document.getElementById('customCategoryGroup').style.display = 'none';
        currentMapCoords = null;
    }

    // Toggle custom category input visibility
    function toggleCustomCategory() {
        const categorySelect = document.getElementById('locationCategory');
        const customCategoryGroup = document.getElementById('customCategoryGroup');
        const customCategoryInput = document.getElementById('customCategory');
        
        if (categorySelect.value === 'Other') {
            customCategoryGroup.style.display = 'block';
            customCategoryInput.focus();
        } else {
            customCategoryGroup.style.display = 'none';
            customCategoryInput.value = '';
        }
    }

    // Handle add location form submission
    function handleAddLocationSubmit(e) {
        e.preventDefault();

        const locationName = document.getElementById('locationName').value.trim();
        let locationCategory = document.getElementById('locationCategory').value;
        const customCategory = document.getElementById('customCategory').value.trim();
        
        // If "Other" is selected, use the custom category
        if (locationCategory === 'Other') {
            if (!customCategory) {
                alert('Please enter a custom category');
                return;
            }
            locationCategory = customCategory;
        }
        
        const lat = parseFloat(document.getElementById('locationLat').value);
        const lng = parseFloat(document.getElementById('locationLng').value);

        if (!locationName) {
            alert('Please enter a location name');
            return;
        }

        if (!locationCategory) {
            alert('Please select a category');
            return;
        }

        if (savedLocations.some(loc => loc.name === locationName)) {
            alert('Location with this name already exists');
            return;
        }

        // Save new location to server via Maps.php API
        const newLocation = {
            name: locationName,
            lat: lat,
            lng: lng,
            category: locationCategory
        };

        // Send to server
        fetch('Maps.php?api=save_custom', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(newLocation)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Save to localStorage as well
                savedLocations.push(newLocation);
                saveLoacations();
                displaySavedLocations();
                
                // Update form with new location
                document.getElementById('in_places').value = locationName;
                update('p_places', locationName);
                calculateDistanceFromFireStation(lat, lng);
                
                alert('✅ Location saved successfully!');
                closeAddLocationModal();
                loadAndDisplayLocations(); // Refresh the locations list
            } else {
                alert('❌ Error saving location. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error saving location:', error);
            alert('❌ Error saving location. Please check the console.');
        });
    }

    // Close modal when clicking outside
    function handleAddLocationModalClick(e) {
        if (e.target.id === 'addLocationModal') {
            closeAddLocationModal();
        }
    }

    // --- Live Sync ---
    function update(id, val) {
        const el = document.getElementById(id);
        if(el) el.innerText = val || "";
    }

    function updatePassengerDisplay(passengerNum) {
        const nameInput = document.getElementById(`in_pass${passengerNum}`);
        const dateInput = document.getElementById(`in_date${passengerNum}`);
        const preview = document.getElementById(`p_pass${passengerNum}`);
        
        const name = nameInput ? nameInput.value : "";
        const date = dateInput ? dateInput.value : "";
        
        let displayText = "";
        if (name && date) {
            displayText = `${name} / ${date}`;
        } else if (name) {
            displayText = name;
        } else if (date) {
            displayText = date;
        }
        
        if (preview) preview.innerText = displayText;
    }

    function calculateGas() {
        const bal = parseFloat(document.getElementById('in_bal').value) || 0;
        const issued = parseFloat(document.getElementById('in_issued').value) || 0;
        const pur = parseFloat(document.getElementById('in_purchased').value) || 0;
        const used = parseFloat(document.getElementById('in_used').value) || 0;
        
        const total = bal + issued + pur;
        const end = total - used;

        document.getElementById('p_bal').innerText = bal || "0";
        document.getElementById('p_issued').innerText = issued || "0";
        document.getElementById('p_purchased').innerText = pur || "0";
        document.getElementById('p_total').innerText = total || "0";
        document.getElementById('p_used').innerText = used || "0";
        document.getElementById('p_end').innerText = end >= 0 ? end : "0";
        
        // Update hidden form fields so they're submitted
        document.getElementById('in_gas_total').value = total || "0";
        document.getElementById('in_gas_balance_end').value = end >= 0 ? end : "0";
    }

    // Haversine formula to calculate distance between two coordinates
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Earth's radius in kilometers
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c; // Distance in kilometers
    }

    // Calculate distance from fire station to selected location
    function calculateDistanceFromFireStation(locationLat, locationLng) {
        const fireStationLat = 10.132752; // Maasin City Fire Station
        const fireStationLng = 124.834795;
        
        // Calculate distance from fire station to location and back
        const distanceOneWay = calculateDistance(fireStationLat, fireStationLng, locationLat, locationLng);
        const roundTripDistance = distanceOneWay * 2; // Round trip distance
        
        // Update the approx_distance field with the calculated distance
        document.getElementById('in_dist').value = roundTripDistance.toFixed(2);
        update('p_dist', roundTripDistance.toFixed(2));
        update('p_speed_dist', roundTripDistance.toFixed(2));
        
        // Update the selected location distance display
        document.getElementById('selectedLocDist').innerText = roundTripDistance.toFixed(2);
    }

    // Display selected location information
    function displaySelectedLocationInfo(location) {
        const display = document.getElementById('selectedLocationDisplay');
        if (!display) return;
        
        document.getElementById('selectedLocName').innerText = location.name;
        document.getElementById('selectedLocLat').innerText = location.lat ? location.lat.toFixed(6) : '--';
        document.getElementById('selectedLocLng').innerText = location.lng ? location.lng.toFixed(6) : '--';
        
        display.style.display = 'block';
    }

    // Expose whether current user is admin to JS
    const isAdmin = <?php echo ($current_role === 'admin') ? 'true' : 'false'; ?>;

    function updateDriver(val) {
        const sel = document.getElementById('in_driver');
        if(sel.selectedIndex <= 0) {
             document.getElementById('p_driver').innerText = "";
             document.getElementById('p_driver_sig').innerText = "";
             clearNotification('driver');
             return;
        }
        const driverName = sel.options[sel.selectedIndex].text;
        document.getElementById('p_driver').innerText = driverName;
        document.getElementById('p_driver_sig').innerText = driverName;
        // Check driver active trip status
        checkDriverStatus(val);
    }

    function updateVehicleFuel(val) {
        const sel = document.getElementById('in_plate');
        if(sel.selectedIndex <= 0) {
             document.getElementById('p_plate').innerText = "";
             document.getElementById('in_bal').value = "";
             clearNotification('vehicle');
             return;
        }
        
        // Get selected option and its fuel data
        const selectedOption = sel.options[sel.selectedIndex];
        const vehicleNo = selectedOption.value;
        const currentFuel = selectedOption.getAttribute('data-fuel');
        
        // Update plate display in preview
        document.getElementById('p_plate').innerText = vehicleNo;
        
        // Auto-populate the "Balance in Tank" field with current fuel level
        document.getElementById('in_bal').value = currentFuel || 0;
        
        // Trigger gas calculation to update preview
        calculateGas();

        // Check vehicle status (on trip / inactive)
        checkVehicleStatus(vehicleNo);
    }

    function checkDriverStatus(driverId) {
        if (!driverId) return;
        fetch('db_connect.php?action=check_driver_trip', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ driver_id: driverId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.is_on_trip) {
                showNotification('⚠️ Driver is currently on an active trip!', 'warning', 'driver');
            } else {
                clearNotification('driver');
            }
        })
        .catch(err => console.error('Driver status check failed', err));
    }

    function checkVehicleStatus(vehicleNo) {
        if (!vehicleNo) return;
        fetch('db_connect.php?action=check_vehicle_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ vehicle_no: vehicleNo })
        })
        .then(r => r.json())
        .then(data => {
            if (data.is_inactive) {
                showNotification('🚫 Vehicle is currently inactive (may be in repair or out of fuel)', 'error', 'vehicle');
            } else if (data.is_on_trip) {
                showNotification('⚠️ Vehicle is currently on an active trip!', 'warning', 'vehicle');
            } else {
                clearNotification('vehicle');
            }
        })
        .catch(err => console.error('Vehicle status check failed', err));
    }

    function showNotification(message, type = 'warning', target = 'driver') {
        let id = target + 'Notification';
        let el = document.getElementById(id);
        if (!el) {
            el = document.createElement('div');
            el.id = id;
            const ref = (target === 'driver') ? document.getElementById('in_driver') : document.getElementById('in_plate');
            if (ref && ref.parentNode) ref.parentNode.insertBefore(el, ref.nextSibling);
        }
        el.style.cssText = 'padding:10px 12px; margin:8px 0; border-radius:8px; font-weight:600;';
        if (type === 'warning') {
            el.style.background = 'rgba(255,193,7,0.08)'; el.style.borderLeft = '4px solid #ffc107'; el.style.color = '#ffc107';
        } else if (type === 'error') {
            el.style.background = 'rgba(255,80,100,0.08)'; el.style.borderLeft = '4px solid #ff5064'; el.style.color = '#ff5064';
        }
        el.innerText = message;
        el.style.display = 'block';
    }

    function clearNotification(target = 'driver') {
        const el = document.getElementById(target + 'Notification');
        if (el) el.style.display = 'none';
    }

    // Form submission - admin code validation removed
    // Users can now submit forms directly

    // ===== LOCATION PICKER FUNCTIONALITY =====
    let locationPickerMap;
    let selectedMarker = null;
    let selectedLocation = null;
    let savedLocations = [];
    let pendingLocationCoords = null;

    // Initialize saved locations from localStorage
    function initSavedLocations() {
        const saved = localStorage.getItem('savedMapLocations');
        if (saved) {
            savedLocations = JSON.parse(saved);
        }
        displaySavedLocations();
    }

    // Display saved locations
    function displaySavedLocations() {
        const container = document.getElementById('mappedLocationsList');
        const section = document.getElementById('mappedLocationsSection');

        if (savedLocations.length === 0) {
            section.style.display = 'none';
            return;
        }

        section.style.display = 'block';
        container.innerHTML = savedLocations.map(loc => `
            <div style="padding: 8px; background: white; border: 1px solid #80bfff; border-radius: 4px; cursor: pointer; transition: all 0.2s; text-align: center; font-size: 12px;" 
                 onmouseover="this.style.background='#e6f2ff'; this.style.boxShadow='0 2px 6px rgba(0,0,0,0.1)'" 
                 onmouseout="this.style.background='white'; this.style.boxShadow='none'"
                 onclick="selectSavedLocation('${loc.name}')">
                <strong>${loc.name}</strong><br>
                <small style="color: #666;">📍 Saved</small><br>
                <button type="button" style="margin-top: 5px; padding: 3px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 10px;" onclick="event.stopPropagation(); deleteSavedLocation('${loc.name}')">Delete</button>
            </div>
        `).join('');
    }

    // Select a saved location
    function selectSavedLocation(name) {
        document.getElementById('in_places').value = name;
        update('p_places', name);
        
        // Find the location and calculate distance
        const location = savedLocations.find(loc => loc.name === name) || maasinLocations.find(loc => loc.name === name);
        if (location) {
            calculateDistanceFromFireStation(location.lat, location.lng);
        }
    }

    // Delete a saved location
    function deleteSavedLocation(name) {
        if (confirm(`Delete "${name}"?`)) {
            savedLocations = savedLocations.filter(loc => loc.name !== name);
            saveLoacations();
            displaySavedLocations();
        }
    }

    // Save locations to localStorage
    function saveLoacations() {
        localStorage.setItem('savedMapLocations', JSON.stringify(savedLocations));
    }

    // Open location detail modal
    function openLocationDetailModal(coords) {
        pendingLocationCoords = coords;
        document.getElementById('locationNameInput').value = '';
        document.getElementById('locationCoordsDisplay').value = `${coords.lat.toFixed(6)}, ${coords.lng.toFixed(6)}`;
        document.getElementById('locationDetailModal').style.display = 'block';
        document.getElementById('locationNameInput').focus();
    }

    // Close location detail modal
    function closeLocationDetailModal() {
        document.getElementById('locationDetailModal').style.display = 'none';
        pendingLocationCoords = null;
    }

    // Save new location
    function saveNewLocation(event) {
        event.preventDefault();
        const name = document.getElementById('locationNameInput').value.trim();
        
        if (!name) {
            alert('Please enter a location name');
            return;
        }

        if (savedLocations.some(loc => loc.name === name)) {
            alert('Location with this name already exists');
            return;
        }

        if (!pendingLocationCoords) {
            alert('No coordinates selected');
            return;
        }

        const newLocation = {
            name: name,
            lat: pendingLocationCoords.lat,
            lng: pendingLocationCoords.lng
        };

        savedLocations.push(newLocation);
        saveLoacations();
        displaySavedLocations();
        closeLocationDetailModal();
        selectSavedLocation(name);
    }

    // Load predefined locations from Maps.php database
    let maasinLocations = [];
    async function loadLocationsFromDatabase() {
        try {
            const response = await fetch('Maps.php?api=get_predefined', { method: 'POST' });
            const data = await response.json();
            if (data.success) {
                maasinLocations = data.locations;
            }
        } catch (error) {
            console.warn('Could not load locations from Maps.php:', error);
            // Fallback predefined locations
            maasinLocations = [
                { name: 'Maasin City Fire Station', lat: 10.132752, lng: 124.834795, category: 'Fire Station' },
                { name: 'Maasin City Park', lat: 10.132377, lng: 124.8387, category: 'Park' },
                { name: 'Maasin Cathedral', lat: 10.132666, lng: 124.837963, category: 'Building' },
                { name: 'Maasin Gaisano Grand Mall', lat: 10.133893, lng: 124.84156, category: 'Building' },
                { name: 'Port of Maasin', lat: 10.131433, lng: 124.841333, category: 'Building' },
                { name: 'Maasin City Terminal', lat: 10.131666, lng: 124.834722, category: 'Building' },
                { name: 'Maasin City Gym', lat: 10.132172, lng: 124.835468, category: 'Building' },
                { name: 'Saint Joseph College', lat: 10.132166, lng: 124.837463, category: 'School' },
                { name: 'Maasin SSS (Social Security System)', lat: 10.133353, lng: 124.845656, category: 'Building' }
            ];
        }
    }

    // Initialize map picker with Leaflet only
    // Load and display all locations from Maps.php database
    async function loadAndDisplayLocations() {
        try {
            const response = await fetch('Maps.php?api=get_all', { method: 'POST' });
            const data = await response.json();
            
            if (data.success && data.locations) {
                displayLocationsList(data.locations);
            }
        } catch (error) {
            console.error('Error loading locations:', error);
            document.getElementById('locationsList').innerHTML = 
                '<div style="text-align: center; color: #999; padding: 10px; font-size: 12px;">Unable to load locations</div>';
        }
    }

    // Display locations in the locations tab
    function displayLocationsList(locations) {
        const listContainer = document.getElementById('locationsList');
        
        if (!locations || locations.length === 0) {
            listContainer.innerHTML = '<div style="text-align: center; color: #999; padding: 10px; font-size: 12px;">No locations available</div>';
            return;
        }

        listContainer.innerHTML = '';
        locations.forEach(loc => {
            const locationCard = document.createElement('div');
            locationCard.className = 'location-card';
            locationCard.innerHTML = `
                <div class="location-card-name">${loc.name}</div>
                <span class="location-card-category">${loc.category}</span>
            `;
            
            locationCard.addEventListener('click', () => {
                selectLocationFromTab(loc);
            });
            
            listContainer.appendChild(locationCard);
        });
    }

    // Select location from the locations tab
    function selectLocationFromTab(location) {
        // Display the location in the form
        document.getElementById('in_places').value = location.name;
        update('p_places', location.name);
        
        // Display location details in the selected location display
        displaySelectedLocationInfo(location);
        
        // Display coordinates and activate "Add New Location" button
        if (location.lat && location.lng) {
            currentMapCoords = {lat: location.lat, lng: location.lng};
            document.getElementById('coordinatesDisplay').style.display = 'block';
            document.getElementById('selectedCoordLat').innerHTML = `Latitude: <strong>${location.lat.toFixed(6)}</strong>`;
            document.getElementById('selectedCoordLng').innerHTML = `Longitude: <strong>${location.lng.toFixed(6)}</strong>`;
            
            // Pan map to this location if map exists
            if (locationPickerMap) {
                locationPickerMap.setView([location.lat, location.lng], 16);
                
                // Add marker for this location
                if (selectedMarker) {
                    locationPickerMap.removeLayer(selectedMarker);
                }
                selectedMarker = L.marker([location.lat, location.lng], {
                    title: location.name
                }).addTo(locationPickerMap);
            }
            
            calculateDistanceFromFireStation(location.lat, location.lng);
        }
        
        mapModal.style.display = 'none';
    }

    function initLocationPicker() {
        // Default to Maasin City Fire Station coordinates
        const maasinCoords = [10.1327, 124.8348];

        if (!locationPickerMap) {
            locationPickerMap = L.map('locationMap').setView(maasinCoords, 15);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(locationPickerMap);

            // Add station marker
            L.marker(maasinCoords, {
                title: 'Maasin City Fire Station'
            }).addTo(locationPickerMap).bindPopup('📍 Maasin City Fire Station');

            // Click on map to select location
            locationPickerMap.on('click', (e) => {
                selectLocationOnMap(e.latlng);
            });

            // Search input functionality
            const searchInput = document.getElementById('mapSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', handleSearchInput);
            }
        }
    }

    // Search location using Nominatim API (OpenStreetMap) + predefined locations
    function handleSearchInput(e) {
        const query = e.target.value.toLowerCase();
        if (query.length < 1) {
            document.getElementById('suggestionsList').innerHTML = '';
            return;
        }

        // First, filter predefined locations
        const filteredPredefined = maasinLocations.filter(loc => 
            loc.name.toLowerCase().includes(query)
        );

        // Display predefined locations first
        displaySuggestions(filteredPredefined);

        // Then fetch from Nominatim API for additional results
        if (query.length >= 2) {
            const nominatimUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&bounded=1&viewbox=124.7,10.2,125.0,10.0&countrycodes=ph`;

            fetch(nominatimUrl)
                .then(response => response.json())
                .then(results => {
                    // Append Nominatim results to predefined ones
                    const suggestionsList = document.getElementById('suggestionsList');
                    if (results.length > 0 && filteredPredefined.length > 0) {
                        const divider = document.createElement('div');
                        divider.style.cssText = 'padding: 5px 10px; background: #e9ecef; font-size: 11px; font-weight: bold; color: #666; margin: 5px 0;';
                        divider.textContent = '--- Other Locations ---';
                        suggestionsList.appendChild(divider);
                    }
                    
                    results.forEach((result) => {
                        const div = document.createElement('div');
                        div.className = 'location-suggestion';
                        div.innerHTML = `<strong>${result.display_name.split(',')[0]}</strong><br><small style="color: #666;">${result.display_name}</small>`;
                        div.addEventListener('click', () => {
                            const lat = parseFloat(result.lat);
                            const lng = parseFloat(result.lon);
                            selectLocationOnMap([lat, lng], result.display_name);
                        });
                        suggestionsList.appendChild(div);
                    });
                })
                .catch(error => console.error('Search error:', error));
        }
    }

    function displaySuggestions(results) {
        const suggestionsList = document.getElementById('suggestionsList');
        
        // Don't clear if we're appending from Nominatim API
        if (!suggestionsList.innerHTML || suggestionsList.textContent.includes('Other Locations') === false) {
            suggestionsList.innerHTML = '';
        }

        if (results.length === 0 && suggestionsList.innerHTML === '') {
            suggestionsList.innerHTML = '<div style="padding: 10px; text-align: center; color: #999;">No results found</div>';
            return;
        }

        // Handle predefined locations (objects with lat/lng properties)
        results.forEach((result) => {
            const div = document.createElement('div');
            div.className = 'location-suggestion';
            
            if (result.lat && result.lng && result.name) {
                // Predefined location
                div.innerHTML = `<strong>${result.name}</strong><br><small style="color: #666;">${result.category || 'Location'}</small>`;
                div.addEventListener('click', () => {
                    selectLocationOnMap([result.lat, result.lng], result.name);
                });
            } else {
                // Nominatim API result
                div.textContent = result.display_name;
                div.addEventListener('click', () => {
                    const lat = parseFloat(result.lat);
                    const lng = parseFloat(result.lon);
                    selectLocationOnMap([lat, lng], result.display_name);
                });
            }
            
            suggestionsList.appendChild(div);
        });
    }

    function selectLocationOnMap(latlng, description = null) {
        // Clear previous marker
        if (selectedMarker) {
            locationPickerMap.removeLayer(selectedMarker);
        }

        // Create new marker
        selectedMarker = L.marker(latlng, {
            title: 'Selected Location'
        }).addTo(locationPickerMap);

        // Center map on marker
        locationPickerMap.setView(latlng, 16);

        // Store the current coordinates for Add New Location
        currentMapCoords = {lat: latlng.lat, lng: latlng.lng};

        // Display coordinates in sidebar
        document.getElementById('coordinatesDisplay').style.display = 'block';
        document.getElementById('selectedCoordLat').innerHTML = `Latitude: <strong>${latlng.lat.toFixed(6)}</strong>`;
        document.getElementById('selectedCoordLng').innerHTML = `Longitude: <strong>${latlng.lng.toFixed(6)}</strong>`;

        // Get address if not provided using reverse geocoding
        if (!description) {
            const nominatimUrl = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latlng.lat}&lon=${latlng.lng}`;
            
            fetch(nominatimUrl)
                .then(response => response.json())
                .then(result => {
                    selectedLocation = {
                        name: result.address?.city || result.address?.town || result.display_name,
                        lat: latlng.lat,
                        lng: latlng.lng
                    };
                    highlightSelectedLocation();
                    // Display selected location info
                    displaySelectedLocationInfo(selectedLocation);
                    // Calculate distance from fire station
                    calculateDistanceFromFireStation(latlng.lat, latlng.lng);
                })
                .catch(error => {
                    // Fallback if reverse geocoding fails
                    selectedLocation = {
                        name: `${latlng.lat.toFixed(6)}, ${latlng.lng.toFixed(6)}`,
                        lat: latlng.lat,
                        lng: latlng.lng
                    };
                    highlightSelectedLocation();
                    // Display selected location info
                    displaySelectedLocationInfo(selectedLocation);
                    // Calculate distance from fire station
                    calculateDistanceFromFireStation(latlng.lat, latlng.lng);
                });
        } else {
            selectedLocation = {
                name: description,
                lat: latlng.lat,
                lng: latlng.lng
            };
            highlightSelectedLocation();
            // Display selected location info
            displaySelectedLocationInfo(selectedLocation);
            calculateDistanceFromFireStation(latlng.lat, latlng.lng);
        }
    }

    function highlightSelectedLocation() {
        // Highlight the suggestion in the list
        document.querySelectorAll('.location-suggestion').forEach(el => {
            el.classList.remove('selected-location');
        });
        
        const suggestions = document.querySelectorAll('.location-suggestion');
        suggestions.forEach(el => {
            if (el.textContent.includes(selectedLocation.name.split(',')[0])) {
                el.classList.add('selected-location');
            }
        });
    }

    // Modal Controls
    const mapModal = document.getElementById('mapModal');
    const mapPickerBtn = document.getElementById('mapPickerBtn');
    const selectLocationBtn = document.getElementById('selectLocationBtn');
    const cancelMapBtn = document.getElementById('cancelMapBtn');
    const closeMapModal = document.querySelector('.close-map-modal');

    if (mapPickerBtn) {
        mapPickerBtn.addEventListener('click', (e) => {
            e.preventDefault();
            mapModal.style.display = 'block';
            setTimeout(() => {
                initLocationPicker();
                loadAndDisplayLocations(); // Load locations in tab
                if (locationPickerMap) {
                    locationPickerMap.invalidateSize();
                }
            }, 100);
        });
    }

    if (selectLocationBtn) {
        selectLocationBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (selectedLocation) {
                document.getElementById('in_places').value = selectedLocation.name;
                update('p_places', selectedLocation.name);
                mapModal.style.display = 'none';
            } else {
                alert('Please select a location on the map first');
            }
        });
    }

    if (cancelMapBtn) {
        cancelMapBtn.addEventListener('click', (e) => {
            e.preventDefault();
            mapModal.style.display = 'none';
        });
    }

    if (closeMapModal) {
        closeMapModal.addEventListener('click', () => {
            mapModal.style.display = 'none';
        });
    }

    // Close modal when clicking outside
    window.addEventListener('click', (e) => {
        if (e.target === mapModal) {
            mapModal.style.display = 'none';
        }
        if (e.target === document.getElementById('locationDetailModal')) {
            closeLocationDetailModal();
        }
        if (e.target === document.getElementById('addLocationModal')) {
            closeAddLocationModal();
        }
    });

    // Location Detail Modal Handlers
    const locationDetailModal = document.getElementById('locationDetailModal');
    const locationDetailForm = document.getElementById('locationDetailForm');
    const closeDetailModal = document.querySelector('.close-detail-modal');
    const cancelDetailBtn = document.getElementById('cancelDetailBtn');
    const addLocationForm = document.getElementById('addLocationForm');

    if (locationDetailForm) {
        locationDetailForm.addEventListener('submit', saveNewLocation);
    }
    if (addLocationForm) {
        addLocationForm.addEventListener('submit', handleAddLocationSubmit);
    }
    
    if (closeDetailModal) {
        closeDetailModal.addEventListener('click', closeLocationDetailModal);
    }
    if (cancelDetailBtn) {
        cancelDetailBtn.addEventListener('click', closeLocationDetailModal);
    }

    // Simple autocomplete for main input field (basic implementation)
    function initPlacesAutocomplete() {
        const input = document.getElementById('in_places');
        let debounceTimer;

        input.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            const query = e.target.value;
            
            if (query.length < 2) {
                return;
            }

            // Simple suggestion using Nominatim
            debounceTimer = setTimeout(() => {
                const nominatimUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&bounded=1&viewbox=124.7,10.2,125.0,10.0&countrycodes=ph`;
                
                fetch(nominatimUrl)
                    .then(response => response.json())
                    .then(results => {
                        // Just update the field value, user can modify as needed
                        if (results.length > 0) {
                            // Show first suggestion in field placeholder-like way
                            input.setAttribute('data-suggestions', JSON.stringify(results.map(r => r.display_name)));
                        }
                    })
                    .catch(error => console.error('Autocomplete error:', error));
            }, 300);
        });
    }

    // Initialize autocomplete when page loads
    document.addEventListener('DOMContentLoaded', () => {
        loadLocationsFromDatabase(); // Load from Maps.php database
        setTimeout(initPlacesAutocomplete, 500);
        initSavedLocations();
        
        // Add Location Modal Handler
        const addLocationForm = document.getElementById('addLocationForm');
        const addLocationModal = document.getElementById('addLocationModal');
        
        if (addLocationForm) {
            addLocationForm.addEventListener('submit', handleAddLocationSubmit);
        }
        
        if (addLocationModal) {
            addLocationModal.addEventListener('click', handleAddLocationModalClick);
        }

        // Style the add location modal inputs to match theme
        const addLocationInputs = document.querySelectorAll('#addLocationModal input, #addLocationModal select');
        addLocationInputs.forEach(input => {
            input.style.cssText = 'width: 100%; padding: 10px 12px; margin-bottom: 10px; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 8px; background: rgba(0, 0, 0, 0.2); color: var(--text-primary); font-family: inherit; transition: all var(--transition-speed) ease; appearance: none;';
            input.addEventListener('focus', function() {
                this.style.borderColor = '#5d5dff';
                this.style.background = 'rgba(93, 93, 255, 0.08)';
            });
            input.addEventListener('blur', function() {
                this.style.borderColor = 'rgba(255, 255, 255, 0.1)';
                this.style.background = 'rgba(0, 0, 0, 0.2)';
            });
        });
    });

</script>
</body>
</html>