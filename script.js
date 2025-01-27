document.addEventListener('DOMContentLoaded', function() {
    const addButton = document.querySelector('.add-product');
    const productList = document.querySelector('.product-entries');

    // Initialize the first entry
    if (productList.querySelector('.product-entry')) {
        const firstEntry = productList.querySelector('.product-entry');
        convertToArrayFields(firstEntry, 0, 0);
    }

    if (addButton && productList) {
        addButton.addEventListener('click', function() {
            const productCount = productList.children.length;
            const firstEntry = productList.querySelector('.product-entry');
            const productEntry = firstEntry.cloneNode(true);

            // Convert fields to array format and clear values
            convertToArrayFields(productEntry, 0, productCount);
            clearFields(productEntry);

            // Add or update remove button
            let removeButton = productEntry.querySelector('.remove-product');
            if (!removeButton) {
                removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'remove-product button';
                productEntry.appendChild(removeButton);
            }
            removeButton.textContent = 'Remove';

            // Update remove button click handler
            removeButton.onclick = function() {
                this.parentElement.remove();
                updateAllIndexes();
            };

            // Add the new entry to the list
            productList.appendChild(productEntry);
        });
    }

    function convertToArrayFields(entry, groupIndex, itemIndex) {
        entry.querySelectorAll('input, textarea').forEach(field => {
            const baseName = field.getAttribute('data-base-name') || field.name;
            // Store the original base name if not already stored
            if (!field.getAttribute('data-base-name')) {
                field.setAttribute('data-base-name', baseName);
            }
            // Update name to nested array format (e.g., 'product-name[0][0]')
            field.name = `${baseName}[${groupIndex}][${itemIndex}]`;
        });
    }

    function clearFields(entry) {
        entry.querySelectorAll('input, textarea').forEach(field => {
            field.value = '';
        });
    }

    function updateAllIndexes() {
        const entries = productList.querySelectorAll('.product-entry');
        entries.forEach((entry, index) => {
            convertToArrayFields(entry, 0, index);
        });
    }
});
