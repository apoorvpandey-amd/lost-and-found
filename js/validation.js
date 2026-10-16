/**
 * FindIt - Client-Side Validation Engine
 * Validates inputs, renders clean visual feedback, and checks file uploads.
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Attach automatic validation to forms with class 'needs-validation'
    const forms = document.querySelectorAll('.needs-validation');

    forms.forEach(form => {
        // Prevent default submit if invalid
        form.addEventListener('submit', function (e) {
            let isValid = validateForm(form);
            if (!isValid) {
                e.preventDefault();
                e.stopPropagation();
                // Focus first invalid element
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstInvalid.focus();
                }
            }
        });

        // Attach live blur/input validation to inputs
        const inputs = form.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            input.addEventListener('blur', function () {
                validateField(input);
            });
            input.addEventListener('input', function () {
                // If it already had error, re-check on typing
                if (input.classList.contains('is-invalid')) {
                    validateField(input);
                }
            });
        });
    });

    // 2. Image upload preview & validation
    initImageUploadPreview();
});

/**
 * Validates all fields within a form
 */
function validateForm(form) {
    let isValid = true;
    const inputs = form.querySelectorAll('input, textarea, select');

    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });

    return isValid;
}

/**
 * Validates a single input element
 */
function validateField(input) {
    // Ignore hidden inputs (except if custom needed)
    if (input.type === 'hidden') return true;

    const value = input.value.trim();
    let errorMessage = '';

    // Required check
    if (input.hasAttribute('required') && !value) {
        errorMessage = 'This field is required.';
    }

    // Email format check
    else if (input.type === 'email' && value) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            errorMessage = 'Please enter a valid email address (e.g. name@domain.com).';
        }
    }

    // Minlength check
    else if (input.getAttribute('minlength') && value.length < parseInt(input.getAttribute('minlength'))) {
        errorMessage = `Must be at least ${input.getAttribute('minlength')} characters long.`;
    }

    // Password confirmation check
    else if (input.id === 'confirm_password' || input.name === 'confirm_password') {
        const passwordInput = document.getElementById('password') || document.querySelector('input[name="password"]');
        if (passwordInput && value !== passwordInput.value) {
            errorMessage = 'Passwords do not match.';
        }
    }

    // Phone format check (optional but validated if provided)
    else if (input.type === 'tel' && value) {
        const phoneRegex = /^[+]?[(]?[0-9]{1,4}[)]?[-\s./0-9]{6,15}$/;
        if (!phoneRegex.test(value)) {
            errorMessage = 'Please enter a valid phone number.';
        }
    }

    // Date check (cannot be future date for lost/found items)
    else if (input.type === 'date' && value) {
        const selectedDate = new Date(value);
        const today = new Date();
        today.setHours(23, 59, 59, 999);
        if (selectedDate > today) {
            errorMessage = 'Date cannot be in the future.';
        }
    }

    // File check
    else if (input.type === 'file' && input.files && input.files.length > 0) {
        const file = input.files[0];
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!allowedTypes.includes(file.type)) {
            errorMessage = 'Allowed formats: JPG, PNG, WEBP, GIF.';
        } else if (file.size > maxSize) {
            errorMessage = 'Image size exceeds maximum limit of 5MB.';
        }
    }

    // Apply error or success state
    return applyFieldState(input, errorMessage);
}

/**
 * Renders error message or clears it
 */
function applyFieldState(input, errorMessage) {
    let errorElem = input.parentElement.querySelector('.field-error');

    if (errorMessage) {
        input.classList.add('is-invalid');
        input.classList.remove('is-valid');

        if (!errorElem) {
            errorElem = document.createElement('span');
            errorElem.className = 'field-error';
            input.parentElement.appendChild(errorElem);
        }
        errorElem.textContent = errorMessage;
        return false;
    } else {
        input.classList.remove('is-invalid');
        if (input.value.trim().length > 0) {
            input.classList.add('is-valid');
        }
        if (errorElem) {
            errorElem.remove();
        }
        return true;
    }
}

/**
 * Interactive Image Upload with Live Preview
 */
function initImageUploadPreview() {
    const fileInput = document.getElementById('item_image');
    const previewContainer = document.getElementById('imagePreviewContainer');
    const previewImg = document.getElementById('imagePreview');
    const removeBtn = document.getElementById('removeImageBtn');

    if (!fileInput || !previewContainer || !previewImg) return;

    fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files[0]) {
            const file = fileInput.files[0];

            // Validate format and size first
            if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type)) {
                alert('Please select an image file (JPG, PNG, WEBP, GIF).');
                fileInput.value = '';
                previewContainer.style.display = 'none';
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                alert('Image file is too large. Max allowed size is 5MB.');
                fileInput.value = '';
                previewContainer.style.display = 'none';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                previewImg.src = e.target.result;
                previewContainer.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    if (removeBtn) {
        removeBtn.addEventListener('click', function (e) {
            e.preventDefault();
            fileInput.value = '';
            previewImg.src = '';
            previewContainer.style.display = 'none';
        });
    }
}
