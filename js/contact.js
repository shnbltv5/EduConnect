/* =========================================================
   contact.js — client-side validation of the contact form
   (About page). PHP checks the same rules again.
   ========================================================= */

const contactForm = document.getElementById("contactForm");

if (contactForm) {
  enableLiveClear(contactForm);
  contactForm.addEventListener("submit", function (event) {
    clearAllErrors(contactForm);
    const name = document.getElementById("contactName");
    const email = document.getElementById("contactEmail");
    const message = document.getElementById("contactMessage");
    let isValid = true;

    if (name.value.trim().length < 2) {
      showError(name, "Please enter your name");
      isValid = false;
    }
    if (isEmpty(email.value)) {
      showError(email, "Please enter your email");
      isValid = false;
    } else if (!isValidEmail(email.value)) {
      showError(email, "Please enter a valid email");
      isValid = false;
    }
    if (message.value.trim().length < 10) {
      showError(message, "Message must be at least 10 characters");
      isValid = false;
    }
    if (!isValid) {
      event.preventDefault();
      focusFirstError(contactForm);
    }
  });
}
