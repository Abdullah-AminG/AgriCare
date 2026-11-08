// public/assets/js/main.js
// Plain JavaScript for small interactions. Server-side validation is still the real protection.

document.addEventListener('DOMContentLoaded', function () {

    // ---- Mobile menu toggle ----
    var menuToggle = document.getElementById('menuToggle');
    var mainNav = document.getElementById('mainNav');

    if (menuToggle && mainNav) {
        menuToggle.addEventListener('click', function () {
            mainNav.classList.toggle('open');
        });
    }

    // ---- Confirmation before destructive actions ----
    // Usage: <form data-confirm="Are you sure?"> ... </form>
    var confirmForms = document.querySelectorAll('form[data-confirm]');
    confirmForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    // ---- Password confirmation check ----
    // Usage: <input data-match="id_of_password_field">
    var matchInputs = document.querySelectorAll('input[data-match]');
    matchInputs.forEach(function (confirmInput) {
        var form = confirmInput.form;
        if (!form) { return; }

        form.addEventListener('submit', function (event) {
            var original = document.getElementById(confirmInput.getAttribute('data-match'));
            if (original && original.value !== confirmInput.value) {
                event.preventDefault();
                window.alert('The two password fields do not match.');
                confirmInput.focus();
            }
        });
    });
});

// ---- Report form: show only the crops that belong to the selected farm ----
// The server still checks that the crop really belongs to the farm.
document.addEventListener('DOMContentLoaded', function () {
    var farmSelect = document.getElementById('report_farm_id');
    var cropSelect = document.getElementById('report_farm_crop_id');

    if (!farmSelect || !cropSelect) { return; }

    // Remember every crop option once, then rebuild the list on each change.
    var allOptions = [];
    for (var i = 0; i < cropSelect.options.length; i++) {
        var option = cropSelect.options[i];
        if (option.value !== '') {
            allOptions.push({
                value: option.value,
                text: option.text,
                farm: option.getAttribute('data-farm'),
                selected: option.selected
            });
        }
    }

    function rebuildCropList() {
        var chosenValue = cropSelect.value;
        cropSelect.innerHTML = '';

        var placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.text = farmSelect.value === '' ? '-- Select a farm first --' : '-- Select crop --';
        cropSelect.appendChild(placeholder);

        allOptions.forEach(function (item) {
            if (item.farm === farmSelect.value) {
                var opt = document.createElement('option');
                opt.value = item.value;
                opt.text = item.text;
                opt.setAttribute('data-farm', item.farm);
                if (item.value === chosenValue) { opt.selected = true; }
                cropSelect.appendChild(opt);
            }
        });
    }

    farmSelect.addEventListener('change', function () {
        cropSelect.value = '';
        rebuildCropList();
    });

    rebuildCropList();
});
