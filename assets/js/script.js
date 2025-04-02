/**
 * Custom JavaScript for the Employee Rating System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Auto-hide alerts after 5 seconds
	var alerts = document.querySelectorAll('.alert:not(.alert-dismissible):not(.persistent-alert)');
	alerts.forEach(function(alert) {
	    setTimeout(function() {
		var fadeEffect = setInterval(function() {
		    if (!alert.style.opacity) {
			alert.style.opacity = 1;
		    }
		    if (alert.style.opacity > 0) {
			alert.style.opacity -= 0.1;
		    } else {
			clearInterval(fadeEffect);
			alert.style.display = 'none';
		    }
		}, 50);
	    }, 5000);
	});

    // Toggle sidebar on small screens
    var sidebarToggle = document.querySelector('.navbar-toggler');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-toggled');
            document.querySelector('.sidebar').classList.toggle('toggled');
        });
    }
    
    // Collapse sidebar when window is small
    if (window.innerWidth < 768) {
        document.querySelector('.sidebar').classList.add('toggled');
    }
    
    // Add active class to current nav item based on URL
    var navLinks = document.querySelectorAll('.nav-link');
    var currentPage = window.location.pathname.split('/').pop();
    
    navLinks.forEach(function(link) {
        var href = link.getAttribute('href');
        if (href && href.indexOf(currentPage) !== -1) {
            link.classList.add('active');
        }
    });
    
    // Enhanced star rating inputs
    var starRatingInputs = document.querySelectorAll('.star-rating-input');
    starRatingInputs.forEach(function(container) {
        var inputs = container.querySelectorAll('input[type="radio"]');
        var labels = container.querySelectorAll('label');
        
        // Apply star icons to labels
        labels.forEach(function(label, index) {
            label.innerHTML = '<i class="bi bi-star"></i>';
            label.addEventListener('mouseover', function() {
                // Highlight all stars up to the hovered one
                for (var i = 0; i <= index; i++) {
                    labels[i].querySelector('i').classList.remove('bi-star');
                    labels[i].querySelector('i').classList.add('bi-star-fill');
                }
                // Un-highlight stars after the hovered one
                for (var j = index + 1; j < labels.length; j++) {
                    labels[j].querySelector('i').classList.remove('bi-star-fill');
                    labels[j].querySelector('i').classList.add('bi-star');
                }
            });
        });
        
        // Reset stars on mouse out from container
        container.addEventListener('mouseout', function() {
            var selectedIndex = -1;
            inputs.forEach(function(input, index) {
                if (input.checked) {
                    selectedIndex = index;
                }
            });
            
            labels.forEach(function(label, index) {
                if (index <= selectedIndex) {
                    label.querySelector('i').classList.remove('bi-star');
                    label.querySelector('i').classList.add('bi-star-fill');
                } else {
                    label.querySelector('i').classList.remove('bi-star-fill');
                    label.querySelector('i').classList.add('bi-star');
                }
            });
        });
        
        // Update star display when an input is selected
        inputs.forEach(function(input, index) {
            input.addEventListener('change', function() {
                if (this.checked) {
                    labels.forEach(function(label, i) {
                        if (i <= index) {
                            label.querySelector('i').classList.remove('bi-star');
                            label.querySelector('i').classList.add('bi-star-fill');
                        } else {
                            label.querySelector('i').classList.remove('bi-star-fill');
                            label.querySelector('i').classList.add('bi-star');
                        }
                    });
                }
            });
            
            // Set initial state for checked inputs
            if (input.checked) {
                for (var i = 0; i <= index; i++) {
                    labels[i].querySelector('i').classList.remove('bi-star');
                    labels[i].querySelector('i').classList.add('bi-star-fill');
                }
            }
        });
    });
    
    // Enhanced data tables with search and pagination
    var dataTable = document.getElementById('dataTable');
    if (dataTable) {
        // Simple search functionality
        var searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'form-control mb-3';
        searchInput.placeholder = 'Search...';
        
        var tableContainer = dataTable.parentNode;
        tableContainer.insertBefore(searchInput, dataTable);
        
        searchInput.addEventListener('keyup', function() {
            var searchText = this.value.toLowerCase();
            var rows = dataTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (var i = 0; i < rows.length; i++) {
                var rowText = rows[i].textContent.toLowerCase();
                if (rowText.indexOf(searchText) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        });
    }
    
    // Form validation for all forms
    var forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                
                // Add validation styles
                var invalidFields = form.querySelectorAll(':invalid');
                invalidFields.forEach(function(field) {
                    // Add error message
                    var errorMessage = field.getAttribute('data-error-message') || 'This field is required';
                    var errorDiv = document.createElement('div');
                    errorDiv.className = 'invalid-feedback';
                    errorDiv.textContent = errorMessage;
                    
                    // Add error styling
                    field.classList.add('is-invalid');
                    
                    // Remove any existing error message
                    var existingError = field.nextElementSibling;
                    if (existingError && existingError.className === 'invalid-feedback') {
                        existingError.remove();
                    }
                    
                    // Add new error message
                    field.parentNode.insertBefore(errorDiv, field.nextSibling);
                });
            }
            
            form.classList.add('was-validated');
        }, false);
        
        // Clear validation styling on input
        var inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(function(input) {
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                var nextElement = this.nextElementSibling;
                if (nextElement && nextElement.className === 'invalid-feedback') {
                    nextElement.remove();
                }
            });
        });
    });
    
    // Confirmation dialog for delete actions
    var deleteButtons = document.querySelectorAll('[data-confirm]');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(event) {
            var message = this.getAttribute('data-confirm') || 'Are you sure you want to delete this item?';
            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });
    
    // Toggle password visibility
    var passwordToggles = document.querySelectorAll('.password-toggle');
    passwordToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            var passwordField = document.getElementById(this.getAttribute('data-password-field'));
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                this.querySelector('i').classList.remove('bi-eye');
                this.querySelector('i').classList.add('bi-eye-slash');
            } else {
                passwordField.type = 'password';
                this.querySelector('i').classList.remove('bi-eye-slash');
                this.querySelector('i').classList.add('bi-eye');
            }
        });
    });
    
    // Print functionality
    var printButtons = document.querySelectorAll('.btn-print');
    printButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            window.print();
        });
    });
    
    // Back to top button
    var backToTopButton = document.createElement('button');
    backToTopButton.id = 'backToTop';
    backToTopButton.innerHTML = '<i class="bi bi-arrow-up"></i>';
    backToTopButton.style.position = 'fixed';
    backToTopButton.style.bottom = '20px';
    backToTopButton.style.right = '20px';
    backToTopButton.style.display = 'none';
    backToTopButton.style.zIndex = '9999';
    backToTopButton.style.width = '40px';
    backToTopButton.style.height = '40px';
    backToTopButton.style.borderRadius = '50%';
    backToTopButton.style.backgroundColor = 'var(--primary-color)';
    backToTopButton.style.color = 'white';
    backToTopButton.style.border = 'none';
    backToTopButton.style.boxShadow = '0 2px 5px rgba(0, 0, 0, 0.2)';
    backToTopButton.style.cursor = 'pointer';
    
    document.body.appendChild(backToTopButton);
    
    backToTopButton.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            backToTopButton.style.display = 'block';
        } else {
            backToTopButton.style.display = 'none';
        }
    });
});
