/* =========================================================
   learn.js — "Mark as completed" button on the lesson page.
   Sends POST api/progress.php with fetch and updates the button,
   the check mark in the lesson list and the progress bar
   WITHOUT reloading the page.
   ========================================================= */

const completeButton = document.getElementById("completeButton");

if (completeButton) {
  completeButton.addEventListener("click", async function () {
    const lessonId = Number(completeButton.dataset.lesson);
    const makeDone = completeButton.dataset.done !== "1";
    const oldText = completeButton.textContent;

    completeButton.disabled = true;
    completeButton.textContent = "Saving...";
    try {
      const data = await apiRequest("api/progress.php", {
        method: "POST",
        body: { lesson_id: lessonId, completed: makeDone }
      });
      // button
      completeButton.dataset.done = data.completed ? "1" : "0";
      completeButton.setAttribute("aria-pressed", data.completed ? "true" : "false");
      completeButton.textContent = data.completed ? "✓ Completed" : "Mark as completed";
      completeButton.classList.toggle("btn--success", data.completed);
      // check mark in the list of lessons
      const item = document.querySelector('.learn__lessons li[data-lesson="' + lessonId + '"] .learn__check');
      if (item) {
        item.textContent = data.completed ? "✓" : item.dataset.icon;
      }
      // progress bar
      const p = data.progress;
      document.getElementById("progressBar").style.width = p.percent + "%";
      document.getElementById("progressText").textContent = p.done + " of " + p.total + " lessons completed (" + p.percent + "%)";
      showToast(data.completed ? "✓ Lesson completed! Progress: " + p.percent + "%" : "Lesson marked as not completed", "success");
    } catch (error) {
      completeButton.textContent = oldText;
      showToast(error.message, "error");
    } finally {
      completeButton.disabled = false;
    }
  });
}
