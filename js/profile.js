/* =========================================================
   profile.js — profile page:
   open / close the edit panels, photo preview (FileReader),
   client-side validation of the profile and password forms.
   ========================================================= */

// ---------- Open / close panels ----------
document.querySelectorAll("[data-open]").forEach(function (button) {
  button.addEventListener("click", function () {
    const panel = document.getElementById(button.dataset.open);
    panel.classList.remove("hidden");
    panel.scrollIntoView({ behavior: "smooth", block: "start" });
    const firstField = panel.querySelector("input:not([type=hidden])");
    if (firstField) {
      firstField.focus();
    }
  });
});

document.querySelectorAll("[data-close]").forEach(function (button) {
  button.addEventListener("click", function () {
    document.getElementById(button.dataset.close).classList.add("hidden");
  });
});

// ---------- Edit profile ----------
const editForm = document.getElementById("editForm");

if (editForm) {
  const photoInput = document.getElementById("editPhoto");
  const preview = document.getElementById("photoPreview");
  enableLiveClear(editForm);

  // show the chosen photo before it is uploaded
  photoInput.addEventListener("change", function () {
    clearError(photoInput);
    preview.classList.add("hidden");
    const file = photoInput.files[0];
    if (!file) {
      return;
    }
    if (!["image/jpeg", "image/png", "image/webp", "image/gif"].includes(file.type)) {
      showError(photoInput, "Please choose an image file (jpg, png, webp, gif)");
      photoInput.value = "";
      return;
    }
    if (file.size > 1024 * 1024) {
      showError(photoInput, "Image is too big — maximum 1 MB");
      photoInput.value = "";
      return;
    }
    const reader = new FileReader();
    reader.onload = function () {
      preview.src = reader.result;
      preview.classList.remove("hidden");
    };
    reader.readAsDataURL(file);
  });

  editForm.addEventListener("submit", function (event) {
    clearAllErrors(editForm);
    const firstName = document.getElementById("editFirstName");
    const lastName = document.getElementById("editLastName");
    const phone = document.getElementById("editPhone");
    const birth = document.getElementById("editBirth");
    let isValid = true;

    if (!isValidName(firstName.value)) {
      showError(firstName, "First name: only letters, at least 2");
      isValid = false;
    }
    if (!isValidName(lastName.value)) {
      showError(lastName, "Last name: only letters, at least 2");
      isValid = false;
    }
    if (!isEmpty(phone.value) && !isValidPhone(phone.value)) {
      showError(phone, "Phone format: +7 701 123 45 67");
      isValid = false;
    }
    if (birth.value !== "" && isFutureDate(birth.value)) {
      showError(birth, "Date of birth cannot be in the future");
      isValid = false;
    }
    if (!isValid) {
      event.preventDefault();
      focusFirstError(editForm);
    }
  });
}

// ---------- Change password ----------
const passwordForm = document.getElementById("passwordForm");

if (passwordForm) {
  enableLiveClear(passwordForm);
  passwordForm.addEventListener("submit", function (event) {
    clearAllErrors(passwordForm);
    const current = document.getElementById("currentPassword");
    const newPassword = document.getElementById("newPassword");
    const confirmPassword = document.getElementById("confirmNewPassword");
    let isValid = true;

    if (isEmpty(current.value)) {
      showError(current, "Enter your current password");
      isValid = false;
    }
    if (!isStrongPassword(newPassword.value)) {
      showError(newPassword, "At least 8 characters with letters and digits");
      isValid = false;
    }
    if (confirmPassword.value !== newPassword.value) {
      showError(confirmPassword, "Passwords do not match");
      isValid = false;
    }
    if (!isValid) {
      event.preventDefault();
      focusFirstError(passwordForm);
    }
  });
}
