<?php
session_start();
// Optionally, check if user is incharge and authenticated
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Get Center Coordinates</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .coordinate-box {
            font-size: 1.2em;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            text-align: center;
        }
        .copy-btn {
            margin-top: 10px;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header text-center">
                    <h4>Get Accurate Center Coordinates</h4>
                </div>
                <div class="card-body">
                    <p class="text-muted text-center">Stand at the center location and click the button below to get your latitude and longitude.</p>
                    <div id="result" class="coordinate-box">
                        <span id="lat">Lat: --</span><br>
                        <span id="lng">Lng: --</span>
                    </div>
                    <button class="btn btn-primary btn-block" onclick="getLocation()">Get Coordinates</button>
                    <button class="btn btn-success btn-block copy-btn" onclick="copyCoordinates()" disabled id="copyBtn">
                        Copy Coordinates
                    </button>
                    <div id="copyMsg" class="text-success text-center mt-2" style="display:none;">Copied!</div>
                    <div class="mt-3 text-center">
                        <small class="text-muted">You can share these coordinates in your WhatsApp group.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            var lat = position.coords.latitude.toFixed(7);
            var lng = position.coords.longitude.toFixed(7);
            document.getElementById('lat').textContent = 'Lat: ' + lat;
            document.getElementById('lng').textContent = 'Lng: ' + lng;
            document.getElementById('copyBtn').disabled = false;
        }, function(error) {
            alert('Unable to get location. Please allow location access and try again.');
        });
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}

function copyCoordinates() {
    var lat = document.getElementById('lat').textContent.replace('Lat: ', '');
    var lng = document.getElementById('lng').textContent.replace('Lng: ', '');
    var text = 'Lat: ' + lat + ', Lng: ' + lng;
    var tempInput = document.createElement('input');
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    document.getElementById('copyMsg').style.display = 'block';
    setTimeout(function() {
        document.getElementById('copyMsg').style.display = 'none';
    }, 1500);
}
</script>
</body>
</html>