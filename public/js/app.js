(function () {
  function applyDecisionFilters() {
    var table = document.getElementById('decision-grid');
    if (!table) return;
    var hide1 = document.getElementById('f-hide-used1');
    var hide2 = document.getElementById('f-hide-used2');
    var hideBoth = document.getElementById('f-hide-both');
    var entryAvail = document.getElementById('f-entry-avail');
    var rows = table.querySelectorAll('tbody tr[data-row]');
    rows.forEach(function (tr) {
      var u1 = tr.getAttribute('data-used1') === '1';
      var u2 = tr.getAttribute('data-used2') === '1';
      var show = true;
      if (hideBoth && hideBoth.checked && u1 && u2) show = false;
      if (hide1 && hide1.checked && u1) show = false;
      if (hide2 && hide2.checked && u2) show = false;
      if (entryAvail && entryAvail.value === '1') {
        if (u1) show = false;
      }
      if (entryAvail && entryAvail.value === '2') {
        if (u2) show = false;
      }
      tr.style.display = show ? '' : 'none';
    });
  }

  ['f-hide-used1', 'f-hide-used2', 'f-hide-both', 'f-entry-avail'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) {
      el.addEventListener('change', applyDecisionFilters);
    }
  });
  applyDecisionFilters();
})();
