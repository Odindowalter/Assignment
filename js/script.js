/* ==========================================================
   AutoCare Garage - service booking page logic
   Functions: validateServiceForm, calculateServiceCost, checkVehicleAge,
              showVehicleInfo, setError, clearErrors, formatKsh
   Events:    submit, change, input, click
   ========================================================== */

/* ---------- Service prices (change charges here) ---------- */
const SERVICES = {
    oil_change:         { label: "Engine Oil Change",  price: 3000 },
    brake_inspection:   { label: "Brake Inspection",   price: 2000 },
    wheel_alignment:    { label: "Wheel Alignment",    price: 2500 },
    computer_diagnosis: { label: "Computer Diagnosis", price: 3500 },
    full_service:       { label: "Full Service",       price: 8000 },
    tyre_balancing:     { label: "Tyre Balancing",     price: 2000 }  // individualization requirement (digit 4)
};

const VEHICLE_INFO = {
    saloon: "Saloon cars: most services are completed on the same day.",
    suv:    "SUVs: please allow extra time for wheel alignment and tyre work.",
    pickup: "Pickups: tell us if the vehicle regularly carries heavy loads.",
    van:    "Vans: heavy-load vehicles may need a longer brake inspection."
};

const OLD_VEHICLE_AGE = 15;   // years before a vehicle is treated as "older"
const OLDEST_YEAR = 1980;     // earliest manufacture year accepted

/* ---------- Element references ---------- */
const form = document.getElementById("serviceForm");
const serviceBoxes = document.querySelectorAll('input[name="services[]"]');
const yearInput = document.getElementById("vehicleYear");
const typeSelect = document.getElementById("vehicleType");

/* ---------- Helpers ---------- */
function formatKsh(amount) {
    return "KSh " + amount.toLocaleString("en-KE");
}

function setError(fieldId, message) {
    document.getElementById("error-" + fieldId).textContent = message;
    document.getElementById(fieldId).classList.add("invalid");
}

function clearErrors() {
    document.querySelectorAll(".error").forEach(function (span) { span.textContent = ""; });
    document.querySelectorAll(".invalid").forEach(function (field) { field.classList.remove("invalid"); });
}

function todayAsString() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");
    return now.getFullYear() + "-" + month + "-" + day;
}

/* ---------- Dynamic behaviour ---------- */

// Shows a short note that depends on the selected vehicle type.
function showVehicleInfo() {
    const box = document.getElementById("vehicleInfo");
    const message = VEHICLE_INFO[typeSelect.value];
    box.textContent = message || "";
    box.classList.toggle("hidden", !message);
}

// Warns when the vehicle is older than OLD_VEHICLE_AGE years.
function checkVehicleAge() {
    const warning = document.getElementById("ageWarning");
    const year = parseInt(yearInput.value, 10);
    const age = new Date().getFullYear() - year;

    if (!isNaN(year) && year >= OLDEST_YEAR && age > OLD_VEHICLE_AGE) {
        warning.textContent = "This vehicle is " + age + " years old. We recommend adding a Computer Diagnosis.";
        warning.classList.remove("hidden");
    } else {
        warning.classList.add("hidden");
    }
}

/* ---------- Cost calculator ---------- */

// Adds up the ticked services, then updates the selected-services list, count and total.
function calculateServiceCost() {
    let total = 0;
    let count = 0;
    let rows = "";

    serviceBoxes.forEach(function (box) {
        if (box.checked) {
            const service = SERVICES[box.value];
            total += service.price;
            count++;
            rows += '<div class="summary-row"><span>' + service.label + "</span><span>" +
                    formatKsh(service.price) + "</span></div>";
        }
    });

    document.getElementById("summaryLines").innerHTML = rows;   // labels are constants, not user text
    document.getElementById("serviceCount").textContent =
        count === 0 ? "No services selected." : count + " service" + (count > 1 ? "s" : "") + " selected:";
    document.getElementById("totalAmount").textContent = formatKsh(total);
    document.getElementById("jsTotal").value = total;
    return total;
}

/* ---------- Validation ---------- */

// Returns true only when every field passes; otherwise shows messages and returns false.
function validateServiceForm() {
    clearErrors();
    let isValid = true;

    const name = document.getElementById("customerName").value.trim();
    const phone = document.getElementById("phone").value.trim();
    const email = document.getElementById("email").value.trim();
    const regNumber = document.getElementById("regNumber").value.trim();
    const model = document.getElementById("vehicleModel").value.trim();
    const year = parseInt(yearInput.value, 10);
    const currentYear = new Date().getFullYear();
    const serviceDate = document.getElementById("serviceDate").value;
    const selectedCount = document.querySelectorAll('input[name="services[]"]:checked').length;

    if (name === "") {
        setError("customerName", "Customer name is required.");
        isValid = false;
    } else if (!/^[A-Za-z][A-Za-z .'-]+$/.test(name)) {
        setError("customerName", "Name may contain only letters, spaces, apostrophes and hyphens.");
        isValid = false;
    }

    if (!/^\d{10}$/.test(phone)) {
        setError("phone", "Phone number must contain exactly 10 digits.");
        isValid = false;
    }

    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
        setError("email", "Enter a valid email address, e.g. name@example.com.");
        isValid = false;
    }

    if (!/^[A-Za-z0-9 -]{4,10}$/.test(regNumber)) {
        setError("regNumber", "Enter the registration number (4-10 letters/digits), e.g. KDA 123A.");
        isValid = false;
    }

    if (typeSelect.value === "") {
        setError("vehicleType", "Please select a vehicle type.");
        isValid = false;
    }

    if (model === "") {
        setError("vehicleModel", "Vehicle model is required.");
        isValid = false;
    }

    if (isNaN(year) || year < OLDEST_YEAR || year > currentYear) {
        setError("vehicleYear", "Enter a year between " + OLDEST_YEAR + " and " + currentYear + ".");
        isValid = false;
    }

    if (serviceDate === "") {
        setError("serviceDate", "Please choose a preferred service date.");
        isValid = false;
    } else if (serviceDate < todayAsString()) {
        setError("serviceDate", "Service date cannot be in the past.");
        isValid = false;
    }

    if (selectedCount === 0) {
        setError("services", "Select at least one service.");
        isValid = false;
    }

    return isValid;
}

/* ---------- Event wiring ---------- */

// submit: block the form unless validation passes
form.addEventListener("submit", function (event) {
    calculateServiceCost();
    if (!validateServiceForm()) {
        event.preventDefault();
        const firstError = document.querySelector(".invalid");
        if (firstError) { firstError.scrollIntoView({ behavior: "smooth", block: "center" }); }
    }
});

// change: services and vehicle type
serviceBoxes.forEach(function (box) { box.addEventListener("change", calculateServiceCost); });
typeSelect.addEventListener("change", showVehicleInfo);

// input: live check while typing the year
yearInput.addEventListener("input", checkVehicleAge);

// click: reset button also clears messages, notices and the estimate
document.getElementById("resetBtn").addEventListener("click", function () {
    setTimeout(function () {
        clearErrors();
        showVehicleInfo();
        checkVehicleAge();
        calculateServiceCost();
    }, 0);
});

// Initial page state
document.getElementById("serviceDate").min = todayAsString();
calculateServiceCost();
