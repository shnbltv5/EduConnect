/* =========================================================
   enroll.js — enrollment form:
   1) AJAX check of the chosen group (api/check_group.php):
      free seats and schedule conflicts are shown immediately;
   2) client-side validation before the form is sent;
   3) live character counter for the comment.
   ========================================================= */

const enrollForm = document.getElementById("enrollForm");

if (enrollForm) {
  const alertBox = document.getElementById("enrollAlert");
  const groupCheck = document.getElementById("groupCheck");
  const submitButton = document.getElementById("enrollSubmit");
  const phone = document.getElementById("enrollPhone");
  const comment = document.getElementById("comment");
  const counter = document.getElementById("commentCounter");
  const terms = document.getElementById("terms");
  let groupIsOk = true;                    // result of the last AJAX check

  enableLiveClear(enrollForm);

  function updateCounter() {
    counter.textContent = comment.value.length + " / 300";
    counter.classList.toggle("char-counter--over", comment.value.length > 300);
  }
  comment.addEventListener("input", updateCounter);
  updateCounter();

  // ---------- AJAX: check the group as soon as it is chosen ----------
  async function checkGroup(groupId) {
    groupCheck.className = "group-check";
    groupCheck.innerHTML = spinnerHtml("Checking your schedule...");
    submitButton.disabled = true;
    try {
      const data = await apiRequest("api/check_group.php?group_id=" + encodeURIComponent(groupId));
      groupIsOk = data.ok;
      groupCheck.className = "group-check " + (data.ok ? "group-check--ok" : "group-check--bad");
      let html = "<p>" + escapeHtml(data.message) + "</p>";
      if (data.conflicts.length > 0) {
        html += "<ul>";
        data.conflicts.forEach(function (c) {
          html += "<li>⚠ " + escapeHtml(c.course) + " — " + escapeHtml(c.day) + " " + escapeHtml(c.time) + "</li>";
        });
        html += "</ul>";
      }
      groupCheck.innerHTML = html;
    } catch (error) {
      // the check is only a hint: if it fails, PHP will still check the group on submit
      groupIsOk = true;
      groupCheck.className = "group-check group-check--bad";
      groupCheck.textContent = "Could not check the group: " + error.message;
    } finally {
      submitButton.disabled = !groupIsOk;
    }
  }

  enrollForm.querySelectorAll("input[name=group_id]").forEach(function (radio) {
    radio.addEventListener("change", function () {
      enrollForm.querySelectorAll(".group-option").forEach(function (option) {
        option.classList.toggle("is-selected", option.contains(radio) && radio.checked);
      });
      checkGroup(radio.value);
    });
  });

  // a group can be already selected after a server error
  const preselected = enrollForm.querySelector("input[name=group_id]:checked");
  if (preselected) {
    preselected.closest(".group-option").classList.add("is-selected");
    checkGroup(preselected.value);
  }

  // ---------- Client-side validation ----------
  enrollForm.addEventListener("submit", function (event) {
    clearAllErrors(enrollForm);
    hideFormAlert(alertBox);
    let isValid = true;

    const group = enrollForm.querySelector("input[name=group_id]:checked");
    if (!group) {
      showError(enrollForm.querySelector("input[name=group_id]"), "Please choose a group (time of classes)");
      isValid = false;
    } else if (!groupIsOk) {
      showFormAlert(alertBox, "The chosen group is not available for you. Please choose another group.", "error");
      isValid = false;
    }

    if (isEmpty(phone.value)) {
      showError(phone, "Phone number is required so the manager can contact you");
      isValid = false;
    } else if (!isValidPhone(phone.value)) {
      showError(phone, "Invalid phone number. Format: +7 701 123 45 67");
      isValid = false;
    }

    if (comment.value.length > 300) {
      showError(comment, "Comment is too long (maximum 300 characters)");
      isValid = false;
    }

    if (!terms.checked) {
      showError(terms, "You must agree to the course terms");
      isValid = false;
    }

    if (!isValid) {
      event.preventDefault();
      focusFirstError(enrollForm);
    }
  });
}
