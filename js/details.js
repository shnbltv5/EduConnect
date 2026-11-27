/* =========================================================
   details.js — course page: send a review with AJAX.
   The new review appears in the list and the average rating
   is updated WITHOUT reloading the page.
   ========================================================= */

const reviewForm = document.getElementById("reviewForm");

if (reviewForm) {
  const comment = document.getElementById("reviewComment");
  const counter = document.getElementById("reviewCounter");
  const submitButton = document.getElementById("reviewSubmit");
  enableLiveClear(reviewForm);

  comment.addEventListener("input", function () {
    counter.textContent = comment.value.length + " / 500";
  });

  reviewForm.addEventListener("submit", async function (event) {
    event.preventDefault();
    clearAllErrors(reviewForm);
    const checked = reviewForm.querySelector("input[name=rating]:checked");
    let isValid = true;

    if (!checked) {
      showError(reviewForm.querySelector("input[name=rating]"), "Choose a rating from 1 to 5 stars");
      isValid = false;
    }
    if (comment.value.trim().length < 5) {
      showError(comment, "Comment must be at least 5 characters");
      isValid = false;
    }
    if (!isValid) {
      return;
    }

    submitButton.disabled = true;
    submitButton.textContent = "Sending...";
    try {
      const data = await apiRequest("api/reviews.php", {
        method: "POST",
        body: { course_id: Number(reviewForm.dataset.course), rating: Number(checked.value), comment: comment.value.trim() }
      });
      addReviewToList(data.review);
      document.getElementById("avgRating").textContent = Number(data.average).toFixed(1);
      document.getElementById("avgStars").textContent = renderStars(data.average);
      document.getElementById("reviewsCount").textContent = data.count;
      reviewForm.remove();                 // one review per course
      showToast("✓ " + data.message, "success");
    } catch (error) {
      // 422: field errors from PHP, 403/409: rule errors
      if (error.data && error.data.errors) {
        if (error.data.errors.rating) {
          showError(reviewForm.querySelector("input[name=rating]"), error.data.errors.rating);
        }
        if (error.data.errors.comment) {
          showError(comment, error.data.errors.comment);
        }
      }
      showToast(error.message, "error");
      submitButton.disabled = false;
      submitButton.textContent = "Send review";
    }
  });
}

function addReviewToList(review) {
  const list = document.getElementById("reviewsList");
  const empty = document.getElementById("noReviews");
  if (empty) {
    empty.remove();
  }
  const item = document.createElement("li");
  item.className = "review review--new";
  item.innerHTML = `
    <div class="review__head"><strong>${escapeHtml(review.author)}</strong>
      <span class="stars">${renderStars(review.rating)}</span>
      <span class="text-muted">${escapeHtml(review.date)}</span></div>
    <p>${escapeHtml(review.comment)}</p>`;
  list.prepend(item);
}
