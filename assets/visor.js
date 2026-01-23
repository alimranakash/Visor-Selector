document.addEventListener("DOMContentLoaded", function () {

    const data = window.visorData || {};
    const products = data.products || [];
    const logos = data.logos || {};
    const settings = data.settings || {};

    const makeSelect = document.getElementById("make-select");
    const modelSelect = document.getElementById("model-select");
    const priceDisplay = document.getElementById("visor-price");
    const addToCartBtn = document.getElementById("add-to-cart");
    const batteryWrap = document.getElementById("battery-colour-wrap");
    const extrasWrap = document.getElementById("extras-wrap");
    const resetBtn = document.getElementById("reset-selection");
    const clearExtrasBtn = document.getElementById("clear-extras");
    const messageDiv = document.getElementById("visor-message");

    // Get dynamic pricing from backend
    const extrasPricing = data.extras_pricing || {};

    let selectedMake = null;
    let selectedModel = null;
    let selectedPack = null;
    let selectedProduct = null;

    if (!makeSelect || !modelSelect || !priceDisplay || !addToCartBtn) {
        console.warn("❌ Required UI elements not found");
        return;
    }

    // Debug: Check if elements are found
    console.log("Price display element:", priceDisplay);
    console.log("Products data:", products.length, "products loaded");
    console.log("Extras pricing:", extrasPricing);
    // Get unique makes and sort them
    let makes = [...new Set(products.map(p => p.make.toLowerCase().trim()))];

    // Separate special makes from regular ones
    const specialMakes = ['universal', 'visin'];
    const regularMakes = makes.filter(make => !specialMakes.includes(make));

    // Sort regular makes alphabetically
    regularMakes.sort();

    // Build final sorted array with special makes at the end
    makes = [...regularMakes];
    if (makes.includes('universal') || products.some(p => p.make.toLowerCase().trim() === 'universal')) {
        makes.push('universal');
    }
    if (makes.includes('visin') || products.some(p => p.make.toLowerCase().trim() === 'visin')) {
        makes.push('visin');
    }

    makes.forEach(make => {
        const btn = document.createElement("button");
        btn.classList.add("make-button");
        btn.type = "button";

        btn.addEventListener("click", function (e) {
            e.preventDefault();
            e.stopPropagation();

            // Remove active class from all make buttons
            document.querySelectorAll('.make-button').forEach(button => {
                button.classList.remove('active');
            });

            // Add active class to clicked button
            this.classList.add('active');

            selectedMake = make;
            selectedModel = null;
            selectedPack = null;
            
            // Check if Best Fit (visin) is selected
            if (make === 'visin') {
                renderCustomHelmetInput();
            } else {
                updateModelDropdown();
            }
        });

        const logo = logos[make.toLowerCase()];
        if (logo) {
            btn.innerHTML = `<img src="${logo}" alt="${make}" style="height:80px;">`;
        } else {
            btn.textContent = make;
        }

        makeSelect.appendChild(btn);
    });

    // Add function to handle custom helmet input for Best Fit
    function renderCustomHelmetInput() {
        const normalizedMake = selectedMake.toLowerCase().trim();
        const filtered = products.filter(p => p.make.toLowerCase().trim() === normalizedMake);
        const models = [...new Set(filtered.map(p => p.model))];

        // Populate model dropdown normally
        modelSelect.innerHTML = "<option>Select Model</option>";
        models.forEach(model => {
            const opt = document.createElement("option");
            opt.value = model;
            opt.textContent = model;
            modelSelect.appendChild(opt);
        });
        
        modelSelect.disabled = false;
        
        // Remove existing custom input if any
        const existingInput = document.getElementById('custom-helmet-wrap');
        if (existingInput) existingInput.remove();
        
        // Create custom input field ABOVE the model dropdown
        const customInputWrap = document.createElement('div');
        customInputWrap.id = 'custom-helmet-wrap';
        customInputWrap.style.marginBottom = '15px';
        customInputWrap.innerHTML = `
            <label style="display: block; margin-bottom: 5px; font-weight: 400; color: #fff;">Please enter the helmet you are purchasing for:</label>
            <input type="text" 
                id="custom-helmet-text" 
                placeholder="e.g. Kilm Krios, Arai Tour X4 etc." 
                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
        `;
        
        // Insert BEFORE the model-select-row div
        const modelRow = document.querySelector('.model-select-row');
        if (modelRow) {
            modelRow.parentNode.insertBefore(customInputWrap, modelRow);
        }
        
        selectedModel = null;
        selectedPack = null;
        priceDisplay.innerHTML = 'YOUR PRICE: £';
        addToCartBtn.disabled = true;
        addToCartBtn.textContent = "SELECT OPTIONS";
        if (batteryWrap) batteryWrap.style.display = "none";
        if (extrasWrap) extrasWrap.style.display = "none";
    }

    function updateModelDropdown() {
        // Remove custom helmet input if exists
        const customWrap = document.getElementById('custom-helmet-wrap');
        if (customWrap) customWrap.remove();
        
        const normalizedMake = selectedMake.toLowerCase().trim();
        const filtered = products.filter(p => p.make.toLowerCase().trim() === normalizedMake);
        const models = [...new Set(filtered.map(p => p.model))];

        modelSelect.innerHTML = "<option>Select Model</option>";
        models.forEach(model => {
            const opt = document.createElement("option");
            opt.value = model;
            opt.textContent = model;
            modelSelect.appendChild(opt);
        });

        modelSelect.disabled = false;
        selectedModel = null;
        selectedPack = null;
        priceDisplay.innerHTML = 'YOUR PRICE: £';
        addToCartBtn.disabled = true;
        addToCartBtn.textContent = "SELECT OPTIONS";
        if (batteryWrap) batteryWrap.style.display = "none";
        if (extrasWrap) extrasWrap.style.display = "none";
    }

    modelSelect.onchange = function () {
        selectedModel = this.value;
        updateProductMatch();
    };

    const modelUnknownBtn = document.getElementById("model-unknown");
    if (modelUnknownBtn) {
        modelUnknownBtn.addEventListener("click", function () {
            selectedModel = "OTHER / UNKNOWN";
            modelSelect.value = ""; // reset actual dropdown
            updateProductMatch();
        });
    }
        
	
    document.querySelectorAll("input[name='pack']").forEach(input => {
        input.addEventListener("change", function () {
            selectedPack = this.value;
            updateProductMatch();
            document.querySelectorAll('.pack-wrap label').forEach(label => {
                label.classList.remove('selected');
            });
            const selectedLabel = document.querySelector(`label[for="${this.id}"]`);
            if (selectedLabel) {
                selectedLabel.classList.add('selected');
            }
        });
    });

    document.querySelectorAll('input[name="battery_colour"]').forEach(radio => {
        radio.addEventListener('change', function () {
            document.querySelectorAll('#battery-colour-wrap label').forEach(label => {
                label.classList.remove('selected');
            });
            const selectedLabel = document.querySelector(`label[for="${this.id}"]`);
            if (selectedLabel) {
                selectedLabel.classList.add('selected');
            }
        });
    });

    // Listen for extras change to update price and highlight active
    document.querySelectorAll("input[name='extras']").forEach(input => {
        input.addEventListener("change", function () {
            updateProductMatch();
            const label = document.querySelector(`label[for="${this.id}"]`);
            if (label) {
                if (this.checked) {
                    label.classList.add('active');
                } else {
                    label.classList.remove('active');
                }
            }
        });
    });

    // Add click handlers to extras labels for better UX
    document.querySelectorAll('.extras-options label').forEach(label => {
        label.addEventListener('click', function(e) {
            e.preventDefault();
            const input = document.getElementById(this.getAttribute('for'));
            if (input) {
                input.checked = !input.checked;
                input.dispatchEvent(new Event('change'));
            }
        });
    });

    document.querySelectorAll('.extras-options label').forEach(label => {
        label.classList.remove('active');
    });
    document.querySelectorAll('input[name="extras"]:checked').forEach(initial => {
        const initialLabel = document.querySelector(`label[for="${initial.id}"]`);
        if (initialLabel) initialLabel.classList.add('active');
    });

    // Validation function
    function validateSelection() {
        // Check if product is selected
        if (!selectedProduct || !selectedProduct.id) {
            return { valid: false, message: "Please select a product first." };
        }

        // Check if make, model, and pack are selected
        if (!selectedMake) {
            return { valid: false, message: "Please select a helmet make." };
        }

        if (!selectedModel) {
            return { valid: false, message: "Please select a helmet model." };
        }

        if (!selectedPack) {
            return { valid: false, message: "Please select a pack type." };
        }

        // Validate Full Pack requirements
        if (selectedPack === "Full Pack") {
            const selectedBattery = document.querySelector('input[name="battery_colour"]:checked');
            if (!selectedBattery) {
                return { valid: false, message: "Please select a Battery Pack Colour for Full Pack." };
            }
        }

        return { valid: true, message: "Validation passed." };
    }

    // Message display functions
    function showMessage(message, type = 'info', duration = 5000) {
        if (!messageDiv) return;

        // Clear existing classes and content
        messageDiv.className = 'visor-message';
        messageDiv.textContent = message;

        // Add type class and show
        messageDiv.classList.add(type, 'show');
        messageDiv.style.display = 'block';

        // Auto-hide after duration
        if (duration > 0) {
            setTimeout(() => {
                hideMessage();
            }, duration);
        }
    }

    function hideMessage() {
        if (!messageDiv) return;

        messageDiv.classList.remove('show');
        setTimeout(() => {
            messageDiv.style.display = 'none';
            messageDiv.className = 'visor-message';
            messageDiv.textContent = '';
        }, 300); // Wait for transition
    }

    function showSuccess(message, duration = 3000) {
        showMessage(message, 'success', duration);
    }

    function showError(message, duration = 5000) {
        showMessage(message, 'error', duration);
    }

    function showInfo(message, duration = 4000) {
        showMessage(message, 'info', duration);
    }



    function updateProductMatch() {
        console.log("updateProductMatch called:", selectedMake, selectedModel, selectedPack);

        if (selectedMake && selectedModel && selectedPack) {
            const normalizedMake = selectedMake.toLowerCase().trim();
            selectedProduct = products.find(p =>
                p.make.toLowerCase().trim() === normalizedMake &&
                p.model === selectedModel &&
                p.pack === selectedPack
            );

            console.log("Selected product:", selectedProduct);

            if (selectedProduct) {
                let finalPrice = parseFloat(selectedProduct.price);
                console.log("Final price:", finalPrice);

                if (selectedPack === "Full Pack") {
                    if (batteryWrap) batteryWrap.style.display = "block";
                    if (extrasWrap) extrasWrap.style.display = "block";

                    const batteryExtra = document.getElementById("extras-battery");
                    const insertExtra = document.getElementById("extras-insert");
                    const insertProduct = products.find(p =>
                        p.make.toLowerCase().trim() === normalizedMake &&
                        p.model === selectedModel &&
                        p.pack === "Insert Only"
                    );

                    // Add extra battery price to main product
                    if (batteryExtra && batteryExtra.checked && extrasPricing["extra-battery"]) {
                        finalPrice += extrasPricing["extra-battery"];
                    }

                    // Calculate display price including extras pricing
                    let displayPrice = finalPrice;
                    let extraInsertPrice = 0;

                    if (insertExtra && insertExtra.checked) {
                        // For insert extra, use the actual insert product price (dynamic pricing)
                        if (insertProduct && insertProduct.price) {
                            extraInsertPrice = parseFloat(insertProduct.price);
                        } else {
                            // Fallback to static pricing if insert product not found
                            extraInsertPrice = extrasPricing["extra-insert"] || 194.99;
                        }
                        displayPrice += extraInsertPrice;
                    }

                    // Update the price display
                    console.log("Updating price display to:", displayPrice);
                    priceDisplay.innerHTML = `YOUR PRICE £${displayPrice.toFixed(2)}`;

                    addToCartBtn.disabled = false;
                    addToCartBtn.textContent = "ADD TO CART";
                    return; // Early return to avoid setting price again below
                } else {
                    if (batteryWrap) batteryWrap.style.display = "none";
                    if (extrasWrap) extrasWrap.style.display = "none";
                }

                console.log("Updating price display (no extras) to:", finalPrice);
                priceDisplay.innerHTML = `YOUR PRICE £${finalPrice.toFixed(2)}`;
                addToCartBtn.disabled = false;
                addToCartBtn.textContent = "ADD TO CART";
            } else {
                priceDisplay.innerHTML = '<strong style="color: #dc3545;">Product Unavailable</strong>';
                addToCartBtn.disabled = true;
                addToCartBtn.textContent = "UNAVAILABLE";
                if (batteryWrap) batteryWrap.style.display = "none";
                if (extrasWrap) extrasWrap.style.display = "none";
            }
        } else {
            priceDisplay.innerHTML = 'YOUR PRICE: £';
            addToCartBtn.disabled = true;
            addToCartBtn.textContent = "SELECT OPTIONS";
        }
    }

    addToCartBtn.addEventListener("click", function () {
        // Comprehensive validation
        const validationResult = validateSelection();
        if (!validationResult.valid) {
            showError(validationResult.message);
            return;
        }

        // Show loading state
        const originalText = addToCartBtn.textContent;
        const originalColor = addToCartBtn.style.backgroundColor;
        addToCartBtn.disabled = true;
        addToCartBtn.textContent = "ADDING TO CART...";
        addToCartBtn.style.backgroundColor = "#0CA5E2";

        // Prepare cart data
        const cartData = {
            action: 'hv_add_to_cart',
            product_id: selectedProduct.id,
            quantity: 1,
            nonce: window.visorData.nonce || '',
            // Add configuration details
            make: selectedMake,
            model: selectedModel,
            pack_type: selectedPack
        };

        // Handle Full Pack specific data
        if (selectedPack === "Full Pack") {
            const selectedBattery = document.querySelector('input[name="battery_colour"]:checked');

            // Add battery color
            if (selectedBattery) {
                cartData.battery_color = selectedBattery.value;
            }

            // Add selected extras
            const selectedExtras = Array.from(
                document.querySelectorAll('input[name="extras"]:checked')
            );
            if (selectedExtras.length > 0) {
                cartData.extras = selectedExtras.map(extra => extra.value);
            }
        }

        const customHelmetText = document.getElementById('custom-helmet-text');
        if (customHelmetText && customHelmetText.value.trim()) {
            cartData.custom_helmet_text = customHelmetText.value.trim();
        }

        // Use AJAX for better user experience
        if (window.visorData && window.visorData.ajax_url) {
            // Properly serialize arrays for PHP
            const formData = new FormData();

            // Add all non-array data
            Object.keys(cartData).forEach(key => {
                if (key === 'extras' && Array.isArray(cartData[key])) {
                    // Handle extras array specially
                    cartData[key].forEach((value, index) => {
                        formData.append(`extras[${index}]`, value);
                    });
                } else {
                    formData.append(key, cartData[key]);
                }
            });

            // AJAX submission
            fetch(window.visorData.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Success feedback
                    addToCartBtn.textContent = "ADDED TO CART!";
                    addToCartBtn.style.backgroundColor = "#28a745";

                    // Show success message
                    showSuccess("✅ " + data.data.message + " Redirecting to checkout...", 2000);

                    // Automatic redirect to checkout after brief delay
                    setTimeout(() => {
                        window.location.href = window.visorData.checkout_url || '/checkout/';
                    }, 2500);

                } else {
                    // Error handling
                    console.error("Cart addition failed:", data.data);
                    showError("❌ " + (data.data || "Failed to add to cart. Please try again."));

                    // Reset button state
                    addToCartBtn.disabled = false;
                    addToCartBtn.textContent = originalText;
                    addToCartBtn.style.backgroundColor = originalColor;
                }
            })
            .catch(error => {
                console.error("AJAX error:", error);
                showError("❌ Network error. Please check your connection and try again.");

                // Reset button state
                addToCartBtn.disabled = false;
                addToCartBtn.textContent = originalText;
                addToCartBtn.style.backgroundColor = originalColor;
            });
        } else {
            // Fallback: Traditional form submission

            const form = document.createElement("form");
            form.method = "POST";
            form.action = window.location.href;
            form.style.display = "none";

            // Add form fields
            Object.keys(cartData).forEach(key => {
                if (key === 'extras' && Array.isArray(cartData[key])) {
                    // Handle extras array
                    cartData[key].forEach(extra => {
                        const input = document.createElement("input");
                        input.type = "hidden";
                        input.name = "extras[]";
                        input.value = extra;
                        form.appendChild(input);
                    });
                } else if (key === 'product_id') {
                    // WooCommerce expects 'add-to-cart' parameter
                    const input = document.createElement("input");
                    input.type = "hidden";
                    input.name = "add-to-cart";
                    input.value = cartData[key];
                    form.appendChild(input);
                } else {
                    const input = document.createElement("input");
                    input.type = "hidden";
                    input.name = key;
                    input.value = cartData[key];
                    form.appendChild(input);
                }
            });

            document.body.appendChild(form);

            try {
                form.submit();
            } catch (error) {
                console.error("Form submission error:", error);
                showError("❌ Error submitting form. Please try again.");

                // Reset button state
                addToCartBtn.disabled = false;
                addToCartBtn.textContent = originalText;
                addToCartBtn.style.backgroundColor = originalColor;
                document.body.removeChild(form);
            }
        }
    });

    // Reset functionality
    if (resetBtn) {
        resetBtn.addEventListener("click", function() {
            // Reset all selections
            selectedMake = null;
            selectedModel = null;
            selectedPack = null;
            selectedProduct = null;

            // Clear make selection visual feedback
            document.querySelectorAll('.make-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Reset model dropdown
            modelSelect.innerHTML = "<option>Select Model</option>";
            modelSelect.disabled = true;

            // Clear pack selection
            document.querySelectorAll('input[name="pack"]').forEach(input => {
                input.checked = false;
            });
            document.querySelectorAll('.pack-wrap label').forEach(label => {
                label.classList.remove('selected');
            });

            // Clear battery selection
            document.querySelectorAll('input[name="battery_colour"]').forEach(input => {
                input.checked = false;
            });
            document.querySelectorAll('#battery-colour-wrap label').forEach(label => {
                label.classList.remove('selected');
            });

            // Clear extras
            clearExtras();

            // Hide sections
            if (batteryWrap) batteryWrap.style.display = "none";
            if (extrasWrap) extrasWrap.style.display = "none";

            // Reset price and button
            priceDisplay.innerHTML = 'YOUR PRICE £XXX.XX';
            addToCartBtn.disabled = true;
            addToCartBtn.textContent = "SELECT OPTIONS";
        });
    }

    // Clear extras functionality
    if (clearExtrasBtn) {
        clearExtrasBtn.addEventListener("click", function() {
            clearExtras();
            updateProductMatch(); // Recalculate price
        });
    }

    function clearExtras() {
        // Uncheck all extras
        document.querySelectorAll('input[name="extras"]').forEach(input => {
            input.checked = false;
        });

        // Remove active styling
        document.querySelectorAll('.extras-options label').forEach(label => {
            label.classList.remove('active');
        });
    }

    // Initial price update
    updateProductMatch();
});