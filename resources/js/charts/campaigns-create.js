export default function init() {
    const $ = (id) => document.getElementById(id);
      const fields = ["campaign-name","objective","budget","ctwa-enabled","ctwa-message","headline","body","destination","interests"];

      function normalizeUrlLabel(value) {
        if (!value) return "yourwebsite.com";
        return value.replace(/^https?:\/\//, "").replace(/\/$/, "");
      }

      function updatePreview() {
        const objective = $("objective").value;
        const ctwa = $("ctwa-enabled").checked;
        $("ctwa-fields").classList.toggle("hidden", !ctwa);
        $("destination-wrap").classList.toggle("opacity-50", ctwa);
        $("ad-headline").textContent = $("headline").value || "Your headline appears here";
        $("ad-body").textContent = $("body").value || "Write ad copy to preview it here.";
        $("ad-url").textContent = ctwa ? "wa.me/" + ($("ctwa-phone").value || "your-number") : normalizeUrlLabel($("destination").value);
        $("ad-cta").textContent = ctwa ? "Send Message" : (objective === "LINK_CLICKS" ? "Learn More" : "Apply Now");
        $("preview-objective-pill").textContent = $("objective").selectedOptions[0].textContent.split(" - ")[0];
        $("phone-message").textContent = $("ctwa-message").value || "Hi, I am interested.";
        $("headline-count").textContent = $("headline").value.length;
        $("body-count").textContent = $("body").value.length;
        const interests = $("interests").value.split(",").map((x) => x.trim()).filter(Boolean);
        $("interest-count").textContent = interests.length;
      }

      fields.forEach((id) => $(id).addEventListener("input", updatePreview));
      $("ctwa-phone").addEventListener("input", updatePreview);
      $("phone-time").textContent = new Date().toTimeString().slice(0,5);

      function activateFileTile(tile) {
        const input = document.createElement("input");
        input.type = "file";
        input.accept = tile.dataset.accept || "";
        input.className = "hidden";
        tile.appendChild(input);
        const titleEl = tile.querySelector(".file-title");
        const subEl = tile.querySelector(".file-sub");
        const actionEl = tile.querySelector(".file-action");
        const iconEl = tile.querySelector(".file-icon");
        const origTitle = titleEl.textContent;
        const origSub = subEl.textContent;
        const origIcon = iconEl.innerHTML;

        tile.addEventListener("click", (e) => {
          if (e.target === actionEl && tile.classList.contains("has-file")) return;
          input.click();
        });
        actionEl.addEventListener("click", (e) => {
          if (!tile.classList.contains("has-file")) return;
          e.stopPropagation();
          input.value = "";
          tile.classList.remove("has-file");
          titleEl.textContent = origTitle;
          subEl.textContent = origSub;
          actionEl.textContent = "Browse";
          actionEl.classList.remove("danger");
          const old = tile.querySelector(".file-thumb, .file-icon");
          const icon = document.createElement("span");
          icon.className = "file-icon w-[34px] h-[34px] rounded-lg bg-[#DFF1ED] text-wa-deep inline-flex items-center justify-center shrink-0";
          icon.innerHTML = origIcon;
          old.replaceWith(icon);
          $("ad-media-img").removeAttribute("src");
          $("ad-media-img").classList.add("hidden");
          $("ad-media-label").textContent = "Image preview";
          $("ad-media-label").classList.remove("hidden");
        });
        input.addEventListener("change", () => {
          const file = input.files && input.files[0];
          if (!file) return;
          tile.classList.add("has-file");
          titleEl.textContent = file.name;
          subEl.textContent = (file.size / 1024).toFixed(1) + " KB / " + (file.type || "image");
          actionEl.textContent = "Remove";
          actionEl.classList.add("danger");
          if (file.type.startsWith("image/")) {
            const reader = new FileReader();
            reader.onload = (ev) => {
              const img = document.createElement("img");
              img.src = ev.target.result;
              img.className = "file-thumb w-[34px] h-[34px] rounded-lg object-cover shrink-0 border border-paper-200";
              tile.querySelector(".file-thumb, .file-icon").replaceWith(img);
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
