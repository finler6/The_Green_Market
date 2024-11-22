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

// UpdateInterestButton
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle-interest').forEach(button => {
        button.addEventListener('click', (e) => {
            const button = e.currentTarget;
            const eventId = button.dataset.eventId;
            const action = button.dataset.action;

            // Отключаем кнопку временно, чтобы исключить повторное нажатие
            button.disabled = true;

            fetch('handle_interests.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ event_id: eventId, action: action }),
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Server error: ${response.status}`);
                    }
                    return response.json();
                })
                .then((data) => {
                    if (data.status === 'success') {
                        // Обновляем текст кнопки
                        if (action === 'add') {
                            button.textContent = 'Remove from Interests';
                            button.classList.remove('btn-primary');
                            button.classList.add('btn-danger');
                            button.dataset.action = 'remove';
                        } else {
                            button.textContent = 'Add to Interests';
                            button.classList.remove('btn-danger');
                            button.classList.add('btn-primary');
                            button.dataset.action = 'add';
                        }
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch((error) => console.error('Error:', error))
                .finally(() => {
                    // Включаем кнопку после завершения
                    button.disabled = false;
                });
        }, { once: true }); // Добавляем параметр once, чтобы слушатель добавлялся только один раз
    });
});


