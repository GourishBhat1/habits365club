<?php
// admin/includes/header.php
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<!-- Including all CSS files -->
<link rel="stylesheet" href="css/simplebar.css">
<link rel="stylesheet" href="css/feather.css">
<link rel="stylesheet" href="css/select2.css">
<link rel="stylesheet" href="css/dropzone.css">
<link rel="stylesheet" href="css/uppy.min.css">
<link rel="stylesheet" href="css/jquery.steps.css">
<link rel="stylesheet" href="css/jquery.timepicker.css">
<link rel="stylesheet" href="css/quill.snow.css">
<link rel="stylesheet" href="css/daterangepicker.css">
<link rel="stylesheet" href="css/dataTables.bootstrap4.css">
<link rel="stylesheet" href="css/app-light.css" id="lightTheme">
<link rel="stylesheet" href="css/app-dark.css" id="darkTheme" disabled">

<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0d6efd">

<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('service-worker.js')
        .then(reg => console.log('Admin Service Worker registered:', reg))
        .catch(err => console.log('Admin SW registration failed:', err));
    });
  }
</script>
