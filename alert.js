(function () {
    const pendingAlerts = [];
    let activeAlert = null;

    function showNextAlert() {
        if (activeAlert || pendingAlerts.length === 0) return;

        const message = pendingAlerts.shift();
        const overlay = document.createElement("div");
        overlay.className = "app-alert-overlay";
        overlay.setAttribute("role", "alertdialog");
        overlay.setAttribute("aria-modal", "true");
        overlay.innerHTML = `
            <div class="app-alert" role="document">
                <div class="app-alert__top">
                    <div class="app-alert__icon" aria-hidden="true">!</div>
                    <h2 class="app-alert__title">MicHost</h2>
                </div>
                <p class="app-alert__message"></p>
                <div class="app-alert__actions">
                    <button class="app-alert__ok" type="button">OK</button>
                </div>
            </div>
        `;
        overlay.querySelector(".app-alert__message").textContent = String(message);
        document.body.appendChild(overlay);
        activeAlert = overlay;

        const closeAlert = function () {
            overlay.classList.remove("is-visible");
            window.setTimeout(function () {
                overlay.remove();
                activeAlert = null;
                showNextAlert();
            }, 180);
        };

        overlay.querySelector(".app-alert__ok").addEventListener("click", closeAlert);
        requestAnimationFrame(function () {
            overlay.classList.add("is-visible");
            overlay.querySelector(".app-alert__ok").focus();
        });
    }

    window.alert = function (message) {
        pendingAlerts.push(message);
        showNextAlert();
    };
})();
