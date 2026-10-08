{{--
    Shows what a new card will carry, straight from the chosen record. Wires up
    every [data-card-form] on the page, so it works for the modal on the Cards
    list and for the standalone create page without duplicating the logic.
--}}
<script>
    (function () {
        document.querySelectorAll('[data-card-form]').forEach(function (form) {
            var select = form.querySelector('select[name="item"]');
            var panel = form.querySelector('[data-card-preview]');

            if (!select || !panel) return;

            function render() {
                var option = select.options[select.selectedIndex];
                var blank = !select.value;

                panel.classList.toggle('hidden', blank);
                if (blank) return;

                ['name', 'code', 'description', 'unit', 'reorder'].forEach(function (field) {
                    var target = panel.querySelector('[data-field="' + field + '"]');
                    if (!target) return;

                    var value = option.getAttribute('data-' + field);

                    if (field === 'reorder') {
                        target.textContent = value && value !== '0' ? value : 'Not set';
                    } else {
                        target.textContent = value || '—';
                    }
                });

                // Suggest the supply's existing reorder level, but never overwrite
                // a value the visitor typed (or one the server sent back).
                var reorderInput = form.querySelector('input[name="reorder_point"]');
                if (reorderInput && !reorderInput.value.trim()) {
                    var reorder = option.getAttribute('data-reorder');
                    reorderInput.value = reorder && reorder !== '0' ? reorder : '';
                }
            }

            select.addEventListener('change', render);
            render();
        });
    })();
</script>
