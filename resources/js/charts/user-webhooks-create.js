export default function init() {
    function addHeader() {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2';
        row.innerHTML = `
          <input type="text" placeholder="Header-Name" class="w-1/3 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep">
          <input type="text" placeholder="value" class="flex-1 px-3 py-2 border border-paper-200 rounded-lg bg-white text-[12.5px] font-mono focus:outline-none focus:border-wa-deep">
          <button type="button" class="w-9 h-9 rounded-lg hover:bg-accent-coral/15 text-accent-coral grid place-items-center"><svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4l8 8M12 4l-8 8"/></svg></button>`;
        row.querySelector('button').onclick = () => row.remove();
        document.getElementById('hdr-list').appendChild(row);
      }
      window.addHeader = addHeader;

      // Event count + summary
      const evList = document.querySelectorAll('.ev');
      function refreshEvents() {
        const checked = [...evList].filter(c => c.checked);
        document.getElementById('ev-count').textContent = checked.length;
        document.getElementById('rv-events').textContent = checked.length;
        if (checked[0]) document.getElementById('rv-event').textContent = checked[0].dataset.ev;
      }
      evList.forEach(c => c.addEventListener('change', refreshEvents));
      document.getElementById('ev-all').addEventListener('click', () => { evList.forEach(c => c.checked = true); refreshEvents(); });
      document.getElementById('ev-none').addEventListener('click', () => { evList.forEach(c => c.checked = false); refreshEvents(); });
}
