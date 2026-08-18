export default function init() {
    const $ = (id) => document.getElementById(id);
      const fields = ["objective","ctwa-enabled","ctwa-phone","headline","body","destination","interests"];

      function normalizeUrlLabel(value) {
        if (!value) return "yourwebsite.com";
        return value.replace(/^https?:\/\//, "").replace(/\/$/, "");
      }

      function updatePreview() {
        const ctwa = $("ctwa-enabled").checked;
        $("ctwa-fields").classList.toggle("hidden", !ctwa);
        $("destination-wrap").classList.toggle("opacity-50", ctwa);
        $("ad-headline").textContent = $("headline").value || "Your headline appears here";
        $("ad-body").textContent = $("body").value || "Write ad copy to preview it here.";
        $("ad-url").textContent = ctwa ? "wa.me/" + ($("ctwa-phone").value || "your-number") : normalizeUrlLabel($("destination").value);
        $("ad-cta").textContent = ctwa ? "Send Message" : "Learn More";
        $("headline-count").textContent = $("headline").value.length;
        $("body-count").textContent = $("body").value.length;
        $("interest-count").textContent = $("interests").value.split(",").map((x) => x.trim()).filter(Boolean).length;
      }

      fields.forEach((id) => $(id).addEventListener("input", updatePreview));
      fields.forEach((id) => $(id).addEventListener("change", updatePreview));

      function activateFileTile(tile) {
        const input = document.createElement("input");
        input.type = "file";
        input.accept = tile.dataset.accept || "";
        input.className = "hidden";
        tile.appendChild(input);
        tile.addEventListener("click", () => input.click());
        input.addEventListener("change", () => {
          const file = input.files && input.files[0];
          if (!file) return;
          tile.querySelector(".file-title").textContent = file.name;
          tile.querySelector(".file-sub").textContent = (file.size / 1024).toFixed(1) + " KB / replacement selected";
          if (file.type.startsWith("image/")) {
            const reader = new FileReader();
            reader.onload = (ev) => {
              $("ad-media-img").src = ev.target.result;
              $("ad-media-img").classList.remove("hidden");
              $("ad-media-label").classList.add("hidden");
            };
            reader.readAsDataURL(file);
          }
        });
      }
      document.querySelectorAll("[data-file-tile]").forEach(activateFileTile);
      updatePreview();
}
