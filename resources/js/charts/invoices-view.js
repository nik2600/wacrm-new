export default function init() {
    function downloadInvoice() {
        // Placeholder — in production this would hit /api/invoices/INV-2026-04821/pdf
        // The print() fallback reuses the print-CSS to produce a perfectly rendered PDF via the browser.
        window.print();
      }
      function emailInvoice() {
        alert('Invoice INV-2026-04821 will be emailed to vetrick@bloomly.in');
      }
}
