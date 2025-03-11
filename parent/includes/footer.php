<?php
// admin/footer.php
?>
<!-- Essential JS Scripts -->
<script src="js/jquery.min.js"></script>
<script src="js/popper.min.js"></script>
<script src="js/moment.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/simplebar.min.js"></script>
<script src="js/daterangepicker.js"></script>
<script src="js/jquery.stickOnScroll.js"></script>
<script src="js/tinycolor-min.js"></script>
<script src="js/config.js"></script>
<script src="js/d3.min.js"></script>
<script src="js/topojson.min.js"></script>
<script src="js/datamaps.all.min.js"></script>
<script src="js/datamaps-zoomto.js"></script>
<script src="js/datamaps.custom.js"></script>
<script src="js/Chart.min.js"></script>
<script>
    Chart.defaults.global.defaultFontFamily = base.defaultFontFamily;
    Chart.defaults.global.defaultFontColor = colors.mutedColor;
</script>
<script src="js/gauge.min.js"></script>
<script src="js/jquery.sparkline.min.js"></script>
<script src="js/apexcharts.min.js"></script>
<script src="js/apexcharts.custom.js"></script>
<script src="js/jquery.mask.min.js"></script>
<script src="js/select2.min.js"></script>
<script src="js/jquery.steps.min.js"></script>
<script src="js/jquery.validate.min.js"></script>
<script src="js/jquery.timepicker.js"></script>
<script src="js/dropzone.min.js"></script>
<script src="js/uppy.min.js"></script>
<script src="js/quill.min.js"></script>
<script src="js/apps.js"></script>
</body>
</html>

<?php
// ✅ Close Database Connection if Open
if (isset($conn)) {
    $conn->close();
}
?>