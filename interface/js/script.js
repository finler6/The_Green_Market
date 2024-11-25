document.addEventListener('DOMContentLoaded', () => {
    initAlerts();
    initReviewModal();
    initRatingStars();
    initProductGallery();
    initToggleInterest();
    initAddToCart();
    initUpdateProduct();
    initDeleteProduct();
});




// Функция для управления уведомлениями
function initAlerts() {
    const alerts = document.querySelectorAll('.alert');
    if (alerts.length > 0) {
        setTimeout(() => {
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0'; // Плавное исчезновение
                setTimeout(() => alert.remove(), 500); // Удаление после анимации
            });
        }, 5000);
    }
}

function initReviewModal() {
    document.querySelectorAll('.leave-review-btn').forEach(button => {
        button.addEventListener('click', function () {
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            document.getElementById('reviewModalLabel').textContent = `Leave a Review for ${productName}`;
            document.getElementById('modalProductId').value = productId;
            document.getElementById('rating').value = '';
            document.getElementById('comment').value = '';
            const ratingStars = document.querySelectorAll('#ratingStars .fa-star');
            ratingStars.forEach(star => {
                star.classList.remove('fa-solid', 'text-warning');
                star.classList.add('fa-regular');
            });
        });
    });
}
// Функция для работы со звёздочками рейтинга
function initRatingStars() {
    const reviewModal = document.getElementById('reviewModal');
    if (reviewModal) {
        const reviewForm = reviewModal.querySelector('form');
        const ratingStars = document.querySelectorAll('.rating-stars .fa-star');
        const ratingInput = document.getElementById('rating');

        ratingStars.forEach((star, index) => {
            star.addEventListener('mouseover', () => highlightStars(index + 1));
            star.addEventListener('mouseout', resetStars);
            star.addEventListener('click', () => setRating(index + 1));
        });

        function highlightStars(rating) {
            ratingStars.forEach((star, idx) => {
                star.classList.toggle('fa-solid', idx < rating);
                star.classList.toggle('fa-regular', idx >= rating);
                star.classList.toggle('text-warning', idx < rating);
            });
        }

        function resetStars() {
            highlightStars(parseInt(ratingInput.value) || 0);
        }

        function setRating(rating) {
            ratingInput.value = rating;
            resetStars();
        }

        resetStars();

        reviewForm.addEventListener('submit', (event) => {
            if (!ratingInput.value) {
                event.preventDefault();
                alert("Please select a rating by clicking on the stars.");
            }
        });
    }
}


function initProductGallery() {
    const galleryContainers = document.querySelectorAll('.product-gallery-container');

    galleryContainers.forEach(galleryContainer => {
        const galleryImagesContainer = galleryContainer.querySelector('.product-gallery-images');
        const galleryImages = galleryImagesContainer.querySelectorAll('img');
        const prevButton = galleryContainer.querySelector('.product-gallery-prev');
        const nextButton = galleryContainer.querySelector('.product-gallery-next');
        let currentImageIndex = 0;

        if (galleryImages.length > 1) {
            function updateGallery() {
                const offset = -currentImageIndex * 100;
                galleryImagesContainer.style.transform = `translateX(${offset}%)`;
            }

            prevButton.addEventListener('click', () => {
                currentImageIndex = (currentImageIndex - 1 + galleryImages.length) % galleryImages.length;
                updateGallery();
            });

            nextButton.addEventListener('click', () => {
                currentImageIndex = (currentImageIndex + 1) % galleryImages.length;
                updateGallery();
            });

            updateGallery();
        } else {
            if (prevButton) prevButton.style.display = 'none';
            if (nextButton) nextButton.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initProductGallery();
});



function initToggleInterest() {
    document.querySelectorAll('.toggle-interest').forEach(button => {
        button.addEventListener('click', (e) => {
            const button = e.currentTarget;
            const eventId = button.dataset.eventId;
            const action = button.dataset.action;

            button.disabled = true;

            fetch('handle_interests.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event_id: eventId, action: action }),
            })
                .then((response) => {
                    if (!response.ok) throw new Error(`Server error: ${response.status}`);
                    return response.json();
                })
                .then((data) => {
                    if (data.status === 'success') {
                        button.textContent = action === 'add' ? 'Remove from Interests' : 'Add to Interests';
                        button.classList.toggle('btn-primary', action === 'remove');
                        button.classList.toggle('btn-danger', action === 'add');
                        button.dataset.action = action === 'add' ? 'remove' : 'add';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(console.error)
                .finally(() => (button.disabled = false));
        });
    });
}
function initAddToCart() {
    const addToCartButtons = document.querySelectorAll('.btn-add-to-cart');
    const modalProductName = document.getElementById('modal-product-name');
    const modalProductId = document.getElementById('modal-product-id');
    const modalQuantity = document.getElementById('modal-quantity');

    addToCartButtons.forEach(button => {
        button.addEventListener('click', () => {
            modalProductId.value = button.dataset.productId;
            modalProductName.value = button.dataset.productName;
            modalQuantity.setAttribute('max', button.dataset.productMax);
            modalQuantity.value = 1;
        });
    });
}
// Универсальная функция для добавления или удаления события из интересов
function updateInterest(eventId, action) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content'); // CSRF токен
    const currentPage = window.location.pathname.endsWith('event.php') ? 'event.php' : 'events.php';

    fetch(currentPage, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            event_id: eventId,
            action: action,
            csrf_token: csrfToken
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Обновляем кнопку
                const button = document.querySelector(`button[onclick="updateInterest(${eventId}, '${action}')"]`);
                if (action === 'add') {
                    button.classList.remove('btn-primary');
                    button.classList.add('btn-danger');
                    button.innerText = 'Remove from Interests';
                    button.setAttribute('onclick', `updateInterest(${eventId}, 'remove')`);
                } else {
                    button.classList.remove('btn-danger');
                    button.classList.add('btn-primary');
                    button.innerText = 'Add to Interests';
                    button.setAttribute('onclick', `updateInterest(${eventId}, 'add')`);
                }
            } else {
                alert(data.error || 'An error occurred.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong. Please try again.');
        });
}



function initUpdateProduct() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    document.querySelectorAll('.product-price, .product-quantity').forEach(input => {
        // Сохраняем начальное значение
        input.dataset.initialValue = input.value;

        input.addEventListener('change', () => {
            const newValue = input.value;
            const initialValue = input.dataset.initialValue;

            // Проверяем, изменилось ли значение
            if (newValue !== initialValue) {
                const field = input.classList.contains('product-price') ? 'price' : 'quantity';
                updateProduct(input, field, csrfToken);

                // Обновляем сохранённое значение после успешного обновления
                input.dataset.initialValue = newValue;
            }
        });
    });

    function updateProduct(input, field, csrfToken) {
        const productId = input.dataset.productId;
        const newValue = field === 'price' ? parseFloat(input.value) : parseInt(input.value);

        if (isNaN(newValue) || newValue <= 0) {
            alert('Please enter a valid value.');
            input.value = input.dataset.initialValue; // Возвращаем к предыдущему значению
            return;
        }

        fetch('update_product.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, [field]: newValue, csrf_token: csrfToken }),
        })
            .then(response => response.json())
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the product.');
            });
    }
}

// Функция для удаления продукта
function initDeleteProduct() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    document.querySelectorAll('.delete-product').forEach(button => {
        button.addEventListener('click', (event) => {
            const productId = button.dataset.productId;

            if (!confirm('Are you sure you want to delete this product?')) return;

            fetch('delete_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId, csrf_token: csrfToken }),
            })
                .then(response => response.json())
                .catch(console.error);
        });
    });
}
