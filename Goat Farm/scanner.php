<?php
require_once 'auth.php';
?>
<div class="card p-4 shadow-sm bg-white border">
    <div class="text-center mb-4">
        <i class="bi bi-camera-fill fs-1 text-primary"></i>
        <h4 class="mt-2 text-uppercase font-weight-bold">Ear & Cage QR Scanner</h4>
        <p class="text-muted small">Align the printed animal QR code directly inside the target bounds below to access their dynamic administrative profile.</p>
    </div>

    <!-- Scanner Target Container -->
    <div class="d-flex justify-content-center">
        <div id="qr-reader" class="border rounded bg-light" style="width: 100%; max-width: 480px;"></div>
    </div>
    
    <div class="text-center mt-3">
        <div id="qr-reader-results" class="text-success fw-bold"></div>
    </div>
</div>

<!-- Load html5-qrcode library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var html5QrcodeScanner = new Html5QrcodeScanner(
        "qr-reader", 
        { fps: 10, qrbox: { width: 250, height: 250 } }, 
        /* verbose= */ false
    );
    
    function onScanSuccess(decodedText, decodedResult) {
        html5QrcodeScanner.clear().then(function() {
            document.getElementById('qr-reader-results').innerText = "Redirecting to Profile...";
            
            // Check if scanned code contains profile query string identifier
            if (decodedText.indexOf('id=') !== -1) {
                var urlParts = decodedText.split('?');
                if (urlParts.length > 1) {
                    var urlParams = new URLSearchParams(urlParts[1]);
                    var animalId = urlParams.get('id');
                    if (animalId) {
                        // Forward automatically to administrative profile route [1]
                        window.location.href = "?page=animals&action=profile&id=" + animalId;
                    } else {
                        alert("Scanned Output: " + decodedText);
                    }
                } else {
                    alert("Scanned Output: " + decodedText);
                }
            } else {
                alert("Scanned Output: " + decodedText);
            }
        }).catch(function(err) {
            console.error("Scanner clear error: ", err);
        });
    }

    function onScanFailure(error) {
        // Continuous scans trigger high volume verbose logs; kept blank to silence console warnings
    }

    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
});
</script>