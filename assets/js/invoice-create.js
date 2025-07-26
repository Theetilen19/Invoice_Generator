document.addEventListener('DOMContentLoaded', function() {
    const businessTypeSelect = document.getElementById('business_type_id');
    const itemsContainer = document.getElementById('itemsContainer');
    const addItemBtn = document.getElementById('addItemBtn');
    
    let itemIndex = 0;
    
    // Templates for different business types
    const templates = {
        1: document.getElementById('freelancingItemTemplate').innerHTML,
        2: document.getElementById('computerSalesItemTemplate').innerHTML,
        3: document.getElementById('ispItemTemplate').innerHTML
    };
    
    // Add item based on business type
    addItemBtn.addEventListener('click', function() {
        const businessTypeId = businessTypeSelect.value;
        
        if (!businessTypeId) {
            alert('Please select a business type first');
            return;
        }
        
        addItem(businessTypeId);
    });
    
    // Add item function
    function addItem(businessTypeId) {
        const template = templates[businessTypeId];
        const html = template.replace(/\{\{index\}\}/g, itemIndex);
        
        const div = document.createElement('div');
        div.innerHTML = html;
        itemsContainer.appendChild(div);
        
        // Add event listeners for freelancing item type changes
        if (businessTypeId == 1) {
            const itemTypeSelect = div.querySelector('.item-type');
            if (itemTypeSelect) {
                itemTypeSelect.addEventListener('change', function() {
                    updateFreelancingFields(div, this.value);
                });
                
                // Initialize fields based on default selection
                updateFreelancingFields(div, itemTypeSelect.value);
            }
        }
        
        // Add remove item functionality
        const removeBtn = div.querySelector('.remove-item');
        if (removeBtn) {
            removeBtn.addEventListener('click', function() {
                itemsContainer.removeChild(div);
            });
        }
        
        itemIndex++;
    }
    
    // Update freelancing fields based on item type
    function updateFreelancingFields(container, itemType) {
        const articleFields = container.querySelector('.article-fields');
        const classFields = container.querySelector('.class-fields');
        const researchFields = container.querySelector('.research-fields');
        
        // Hide all fields first
        articleFields.classList.add('d-none');
        classFields.classList.add('d-none');
        researchFields.classList.add('d-none');
        
        // Show relevant fields
        switch (itemType) {
            case 'article':
                articleFields.classList.remove('d-none');
                break;
            case 'class':
                classFields.classList.remove('d-none');
                break;
            case 'research':
                researchFields.classList.remove('d-none');
                break;
        }
    }
    
    // Business type change handler
    businessTypeSelect.addEventListener('change', function() {
        // Clear existing items when business type changes
        itemsContainer.innerHTML = '';
        itemIndex = 0;
    });
    
    // Add one item by default if business type is selected
    if (businessTypeSelect.value) {
        addItem(businessTypeSelect.value);
    }
});