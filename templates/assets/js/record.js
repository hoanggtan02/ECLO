// record.js

// Hàm khởi tạo toàn bộ logic
function initializeRecordHandler() {
  if (window.recordHandlerInitialized) {
    return;
  }
  window.recordHandlerInitialized = true;

  // Gắn sự kiện cho [data-record-action='modal'] (dành riêng cho lịch)
  $(document).off("click", "[data-record-action='modal']"); // Xóa sự kiện cũ
  $(document).on("click", "[data-record-action='modal']", function (e) {
    e.preventDefault();
    const url = $(this).data("url");
    console.log("Record modal triggered for URL:", url); // Log để kiểm tra
    openModal(url);
  });

  function openModal(url) {
    const existingModals = document.querySelectorAll(".modal");
    existingModals.forEach((modal) => modal.remove());

    fetch(url)
      .then((response) => {
        if (!response.ok) throw new Error("Network error: " + response.status);
        return response.text();
      })
      .then((html) => {
        const modalContainer = document.createElement("div");
        modalContainer.innerHTML = html;
        document.body.appendChild(modalContainer);
        const modalElement = modalContainer.querySelector(".modal");
        if (modalElement) {
          const modal = new bootstrap.Modal(modalElement);
          modal.show();
          modalElement.addEventListener("hidden.bs.modal", () => {
            modalContainer.remove();
          });
        }
      })
      .catch((error) => {
        console.error("Error loading modal:", error);
        alert("Không thể tải dữ liệu!");
      });
  }

  // Logic lịch trong closure
  (function () {
    let currentDate = new Date();
    let currentMonth = currentDate.getMonth();
    let currentYear = currentDate.getFullYear();

    function renderCalendar(datesWithRecords, timekeepingByDate) {
      const firstDayOfMonth = new Date(currentYear, currentMonth, 1);
      const lastDayOfMonth = new Date(currentYear, currentMonth + 1, 0);
      const firstDayOfWeek = firstDayOfMonth.getDay();
      const totalDays = lastDayOfMonth.getDate();

      const currentMonthElement = document.getElementById("current-month");
      if (currentMonthElement) {
        currentMonthElement.textContent = `${currentMonth + 1}/${currentYear}`;
      }

      let calendarBody = document.getElementById("calendar-body");
      if (!calendarBody) {
        console.error("Calendar body not found!");
        return;
      }

      calendarBody.innerHTML = "";
      let row = document.createElement("tr");
      let dayCount = 1;

      for (let i = 0; i < firstDayOfWeek; i++) {
        let cell = document.createElement("td");
        cell.classList.add("text-muted");
        row.appendChild(cell);
      }

      for (let i = firstDayOfWeek; i < 7; i++) {
        if (dayCount > totalDays) break;
        let cell = document.createElement("td");
        cell.textContent = dayCount;
        const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(
          2,
          "0"
        )}-${String(dayCount).padStart(2, "0")}`;
        if (datesWithRecords.includes(dateStr)) {
          cell.classList.add("has-records");
          cell.style.backgroundColor = "#28a745";
          cell.style.color = "#fff";
          cell.style.cursor = "pointer";
          cell.dataset.date = dateStr;
          cell.addEventListener("click", (event) =>
            showTimekeepingDetails(event, timekeepingByDate)
          );
        }
        row.appendChild(cell);
        dayCount++;
      }
      calendarBody.appendChild(row);

      while (dayCount <= totalDays) {
        row = document.createElement("tr");
        for (let i = 0; i < 7; i++) {
          if (dayCount > totalDays) {
            let cell = document.createElement("td");
            cell.classList.add("text-muted");
            row.appendChild(cell);
          } else {
            let cell = document.createElement("td");
            cell.textContent = dayCount;
            const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(
              2,
              "0"
            )}-${String(dayCount).padStart(2, "0")}`;
            if (datesWithRecords.includes(dateStr)) {
              cell.classList.add("has-records");
              cell.style.backgroundColor = "#28a745";
              cell.style.color = "#fff";
              cell.style.cursor = "pointer";
              cell.dataset.date = dateStr;
              cell.addEventListener("click", (event) =>
                showTimekeepingDetails(event, timekeepingByDate)
              );
            }
            row.appendChild(cell);
          }
          dayCount++;
        }
        calendarBody.appendChild(row);
      }
    }

    function showTimekeepingDetails(event, timekeepingByDate) {
      const selectedDate = event.target.dataset.date;
      const records = timekeepingByDate[selectedDate] || [];

      const selectedDateElement = document.getElementById("selected-date");
      if (selectedDateElement) {
        selectedDateElement.textContent = selectedDate;
      }

      const tableBody = document.getElementById("timekeeping-table-body");
      if (!tableBody) {
        console.error("Timekeeping table body not found!");
        return;
      }
      tableBody.innerHTML = "";

      if (records.length > 0) {
        records.forEach((record) => {
          const row = document.createElement("tr");
          row.innerHTML = `
              <td>${record.id}</td>
              <td>${record.time}</td>
            `;
          tableBody.appendChild(row);
        });
      } else {
        const row = document.createElement("tr");
        row.innerHTML = `<td colspan="2">Không có dữ liệu chấm công trong ngày này</td>`;
        tableBody.appendChild(row);
      }

      const timekeepingDetails = document.getElementById("timekeeping-details");
      if (timekeepingDetails) {
        timekeepingDetails.style.display = "block";
      }
    }

    function setupCalendarNavigation(datesWithRecords, timekeepingByDate) {
      const prevMonthBtn = document.getElementById("prev-month");
      const nextMonthBtn = document.getElementById("next-month");

      if (prevMonthBtn) {
        const newPrevMonthBtn = prevMonthBtn.cloneNode(true);
        prevMonthBtn.parentNode.replaceChild(newPrevMonthBtn, prevMonthBtn);
        newPrevMonthBtn.addEventListener("click", () => {
          currentMonth--;
          if (currentMonth < 0) {
            currentMonth = 11;
            currentYear--;
          }
          renderCalendar(datesWithRecords, timekeepingByDate);
        });
      }

      if (nextMonthBtn) {
        const newNextMonthBtn = nextMonthBtn.cloneNode(true);
        nextMonthBtn.parentNode.replaceChild(newNextMonthBtn, nextMonthBtn);
        newNextMonthBtn.addEventListener("click", () => {
          currentMonth++;
          if (currentMonth > 11) {
            currentMonth = 0;
            currentYear++;
          }
          renderCalendar(datesWithRecords, timekeepingByDate);
        });
      }
    }

    document.addEventListener("shown.bs.modal", function (event) {
      const modal = event.target;
      if (modal.classList.contains("modal-load")) {
        const datesWithRecords = JSON.parse(
          modal.dataset.datesWithRecords || "[]"
        );
        const timekeepingByDate = JSON.parse(
          modal.dataset.timekeepingByDate || "{}"
        );

        console.log("Dates with records:", datesWithRecords);
        console.log("Timekeeping by date:", timekeepingByDate);

        currentDate = new Date();
        currentMonth = currentDate.getMonth();
        currentYear = currentDate.getFullYear();

        renderCalendar(datesWithRecords, timekeepingByDate);
        setupCalendarNavigation(datesWithRecords, timekeepingByDate);
      }
    });
  })();
}

document.addEventListener("DOMContentLoaded", function () {
  initializeRecordHandler();
});
