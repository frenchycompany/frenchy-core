<script>
    function receiveMessage(event) {
        if (event.data.type === "setHeight") {
            document.getElementById("landingFrame").style.height = event.data.height + "px";
        }
    }

    window.addEventListener("message", receiveMessage, false);
</script>

<iframe 
    id="landingFrame"
    src="https://frenchyconciergerie.fr/cdansmaville/admin/landing.php?client_id=15"
    width="100%" 
    style="border: none;"
    scrolling="no">
</iframe>
