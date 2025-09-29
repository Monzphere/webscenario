/**
 * WebScenario Manager JavaScript
 */


document.addEventListener('DOMContentLoaded', function() {
    

    
    initializeWebScenarioList();
});

function initializeWebScenarioList() {
    
    const filterForm = document.querySelector('.filter-container form');
    if (filterForm) {
        
        const filterInputs = filterForm.querySelectorAll('input, select');
        filterInputs.forEach(input => {
            if (input.type === 'text') {
                let timeout;
                input.addEventListener('input', function() {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => {
                        filterForm.submit();
                    }, 500);
                });
            } else {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            }
        });
    }

    
    const actionButtons = document.querySelectorAll('.btn-action');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const action = this.getAttribute('data-action');
            const selectedItems = document.querySelectorAll('input[name^="webscenario_ids"]:checked');

            if (selectedItems.length === 0) {
                e.preventDefault();
                alert('Please select at least one web scenario.');
                return false;
            }

            
            let confirmMessage = '';
            switch (action) {
                case 'delete':
                    confirmMessage = `Are you sure you want to delete ${selectedItems.length} web scenario(s)?`;
                    break;
                case 'enable':
                    confirmMessage = `Are you sure you want to enable ${selectedItems.length} web scenario(s)?`;
                    break;
                case 'disable':
                    confirmMessage = `Are you sure you want to disable ${selectedItems.length} web scenario(s)?`;
                    break;
            }

            if (confirmMessage && !confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        });
    });
}


function checkAll(formName, masterCheckbox, slaveCheckboxes) {
    const form = document.forms[formName];
    const master = form.elements[masterCheckbox];
    const slaves = form.querySelectorAll('input[name^="' + slaveCheckboxes + '"]');

    slaves.forEach(slave => {
        slave.checked = master.checked;
    });
}