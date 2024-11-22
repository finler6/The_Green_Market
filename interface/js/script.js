document.addEventListener('DOMContentLoaded', () => {
    const reviewModal = document.getElementById('reviewModal');
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
});
