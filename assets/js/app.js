// Shared chart helpers (Chart.js is loaded before this file)
var CIQ = {
  c: { red: '#e5484d', amber: '#f0a020', green: '#14a96b', primary: '#5b4bff', primary2: '#9a90ff', blue: '#2f80ed', violet: '#8b5cf6', slate: '#9a9fc4', line: '#e4e6f2' },
  segColors: { 'High Performer': '#14a96b', 'Critical Intervention': '#e5484d', 'Placement Gap': '#f0a020', 'Attendance Risk': '#fbc15c', 'Skill Gap': '#8b5cf6', 'Potential Improver': '#2f80ed', 'Balanced': '#9a9fc4' },
  chart: function (id, cfg) {
    var el = document.getElementById(id);
    if (!el || typeof Chart === 'undefined') return null;
    Chart.defaults.font.family = 'Manrope, system-ui, sans-serif';
    Chart.defaults.color = '#6a6f94';
    cfg.options = cfg.options || {};
    cfg.options.responsive = true;
    cfg.options.maintainAspectRatio = false;
    return new Chart(el, cfg);
  },
  grid: { color: '#eceef7', drawBorder: false }
};
document.addEventListener('DOMContentLoaded', function () {
  // close mobile sidebar when navigating
  document.querySelectorAll('.side nav a').forEach(function (a) {
    a.addEventListener('click', function () { document.getElementById('side').classList.remove('open'); });
  });
  // client-side table filter: <input data-filter="tableId">
  document.querySelectorAll('[data-filter]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var q = inp.value.toLowerCase();
      document.querySelectorAll('#' + inp.dataset.filter + ' tbody tr').forEach(function (tr) {
        tr.style.display = tr.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none';
      });
    });
  });
});
