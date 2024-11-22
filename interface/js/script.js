document.addEventListener('DOMContentLoaded', () => {
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
});

document.addEventListener('DOMContentLoaded', () => {
    // Код для управления звёздочками рейтинга
    const reviewModal = document.getElementById('reviewModal');
    if (reviewModal) {
        const reviewForm = reviewModal.querySelector('form');
        const ratingStars = document.querySelectorAll('.rating-stars .fa-star');
        const ratingInput = document.getElementById('rating');

        // Управление звёздочками
        ratingStars.forEach((star, index) => {
            star.addEventListener('mouseover', () => {
                highlightStars(index + 1);
            });

            star.addEventListener('mouseout', () => {
                resetStars();
            });

            star.addEventListener('click', () => {
                setRating(index + 1);
            });
        });

        function highlightStars(rating) {
            ratingStars.forEach((star, idx) => {
                if (idx < rating) {
                    star.classList.add('fa-solid', 'text-warning');
                    star.classList.remove('fa-regular');
                } else {
                    star.classList.add('fa-regular');
                    star.classList.remove('fa-solid', 'text-warning');
                }
            });
        }

        function resetStars() {
            const currentRating = parseInt(ratingInput.value) || 0;
            highlightStars(currentRating);
        }

        function setRating(rating) {
            ratingInput.value = rating; // Устанавливаем значение в скрытое поле
            resetStars();
        }

        // Сбрасываем звёздочки при загрузке
        resetStars();

        // Проверка значения рейтинга перед отправкой формы
        reviewForm.addEventListener('submit', (event) => {
            if (!ratingInput.value) {
                event.preventDefault();
                alert("Please select a rating by clicking on the stars.");
            }
        });
    }

    const galleryImagesContainer = document.querySelector('.gallery-images');
    const galleryImages = galleryImagesContainer ? galleryImagesContainer.querySelectorAll('img') : [];
    const prevButton = document.querySelector('.gallery-prev');
    const nextButton = document.querySelector('.gallery-next');
    let currentImageIndex = 0;

    if (galleryImages.length > 1) {
        // Устанавливаем ширину контейнера для всех изображений
        galleryImagesContainer.style.width = `${galleryImages.length * 100}%`;

        // Каждое изображение занимает 100% ширины контейнера
        galleryImages.forEach(img => {
            img.style.width = `${100 / galleryImages.length}%`;
            img.style.flexShrink = "0";
        });

        // Обновляем отображение активного изображения
        function updateGallery() {
            const offset = -currentImageIndex * (100 / galleryImages.length); // Рассчитываем сдвиг
            galleryImagesContainer.style.transform = `translateX(${offset}%)`;
        }

        // Переход к предыдущему изображению
        prevButton.addEventListener('click', () => {
            currentImageIndex = (currentImageIndex - 1 + galleryImages.length) % galleryImages.length;
            updateGallery();
        });

        // Переход к следующему изображению
        nextButton.addEventListener('click', () => {
            currentImageIndex = (currentImageIndex + 1) % galleryImages.length;
            updateGallery();
        });

        // Инициализация галереи
        updateGallery();
    } else {
        // Если только одно изображение, скрываем кнопки
        if (prevButton) prevButton.style.display = 'none';
        if (nextButton) nextButton.style.display = 'none';
    }
});

// updateInterest - функция для добавления или удаления события из интересов
function updateInterest(eventId, action) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content'); // CSRF токен

    fetch('events.php', {
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
