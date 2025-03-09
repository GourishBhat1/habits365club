document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("form");
    if (form) {
        form.addEventListener("submit", function () {
            // Create preloader div
            const preloader = document.createElement("div");
            preloader.className = "preloader-overlay";
            preloader.innerHTML = `
                <div class="preloader">
                    <div class="spinner"></div>
                    <p>Uploading... Please wait</p>
                </div>
            `;
            document.body.appendChild(preloader);

            // Disable submit button to prevent multiple clicks
            const submitButton = form.querySelector("button[type='submit']");
            if (submitButton) {
                submitButton.disabled = true;
            }
        });
    }
});