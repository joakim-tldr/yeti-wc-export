(function (window, document, undefined) {
  "use strict";

  const YWCE_DEBUG = {
    isDevEnvironment: function () {
      return (
        window.location.hostname === "localhost" ||
        window.location.hostname === "127.0.0.1" ||
        window.location.hostname.includes(".local") ||
        window.location.hostname.includes(".test") ||
        this.isDebugForced()
      );
    },

    isDebugForced: function () {
      const urlParams = new URLSearchParams(window.location.search);
      return urlParams.has("ywce_debug") && urlParams.get("ywce_debug") === "1";
    },

    log: function () {
      if (
        (this.isDevEnvironment() || this.isDebugForced()) &&
        window.console &&
        console.log
      ) {
        console.log.apply(console, arguments);
      }
    },

    error: function () {
      if (
        (this.isDevEnvironment() || this.isDebugForced()) &&
        window.console &&
        console.error
      ) {
        console.error.apply(console, arguments);
      }
    },
  };

  window.YWCE = window.YWCE || {};

  YWCE.debug = YWCE_DEBUG;

  YWCE.init = function () {
    YWCE.debug.log("YWCE: Initializing...");

    if (typeof bootstrap === "undefined") {
      YWCE.debug.error(
        "Bootstrap is not loaded! Please check your script includes."
      );
      return;
    }

    YWCE.debug.log("YWCE: AJAX URL:", ywce_ajax.ajax_url);
    YWCE.debug.log("YWCE: Nonce available:", !!ywce_ajax.nonce);

    if (YWCE.debug.isDevEnvironment() || YWCE.debug.isDebugForced()) {
      const debugButton = document.getElementById("ywce-debug-btn");
      if (debugButton) {
        debugButton.classList.remove("d-none");
        YWCE.debug.log("YWCE: Debug button enabled");

        if (YWCE.debug.isDebugForced()) {
          debugButton.classList.add("btn-warning");
          debugButton.classList.remove("btn-outline-secondary");
          YWCE.debug.log("YWCE: Debug mode forced via URL parameter");
        }
      }
    }

    const tooltipTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="tooltip"]')
    );
    tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    const i18n = ywce_ajax.i18n || {};

    YWCE.__ = function (text, fallback) {
      return i18n[text] || fallback || text;
    };

    YWCE.state = {
      currentStep: 1,
      selectedDataSource: null,
      selectedFields: [],
      selectedMeta: [],
      selectedTaxonomies: [],
      lastPreviewData: [],
      selectedHeaders: {},
      selectedColumns: [],
      hasVariableProducts: false,
      draggingColumn: null,
      selectedFormats: ["csv"],
      exportId: null,
      isExporting: false,
      fieldsModifiedAfterExport: false,
      availableProductTypes: [],
      availableUserRoles: [],
      availableOrderStatuses: [],
      selectedProductType: "all",
      selectedUserRole: "all",
      selectedOrderStatus: "all",
      exportQueue: [],
      currentExportIndex: 0,
    };

    YWCE.elements = {
      nextStepButton: document.getElementById("ywce-next-step"),
      exportNameInput: document.getElementById("export-name"),
      generateNameButton: document.getElementById("generate-name"),
      exportButton: document.getElementById("ywce-export-btn"),
      formatButtons: document.querySelectorAll(".format-btn"),
      exportSummary: document.getElementById("ywce-export-summary-content"),
      exportProgressContainer: document.getElementById(
        "ywce-export-progress-container"
      ),
      exportProgress: document.querySelector(
        "#ywce-export-progress-container .progress-bar"
      ),
      exportStatus: document.getElementById("ywce-export-status"),
      downloadLinks: document.getElementById("ywce-download-links"),
    };

    if (YWCE.elements.nextStepButton) {
      YWCE.elements.nextStepButton.disabled = true;
    }

    YWCE.initEventListeners();

    YWCE.showStep(1);

    YWCE.debugState();
  };

  YWCE.showStep = function (step) {
    YWCE.debug.log("YWCE: Showing step:", step);

    if (step < 1 || step > 4) {
      YWCE.debug.error("Invalid step number:", step);
      return;
    }

    YWCE.state.currentStep = step;

    if (step === 1 || step === 2) {
      YWCE.state.fieldsModifiedAfterExport = true;
    }

    const steps = document.querySelectorAll(".step");
    YWCE.debug.log("YWCE: Found", steps.length, "step elements");

    if (steps.length > 0) {
      steps.forEach((el) => {
        el.classList.remove("active");
        YWCE.debug.log("YWCE: Removed active class from step:", el.className);
      });

      const currentStep = document.querySelector(`.step-${step}`);
      if (currentStep) {
        currentStep.classList.add("active");
        YWCE.debug.log(
          "YWCE: Added active class to step:",
          currentStep.className
        );
      } else {
        YWCE.debug.error(`Step ${step} element not found`);
        return;
      }
    } else {
      YWCE.debug.error("No step elements found");
      return;
    }

    YWCE.updateProgressBar(step);
    YWCE.updateButtons(step);

    if (step === 2) {
      YWCE.debug.log("YWCE: Fetching data fields for step 2");
      YWCE.fetchDataFields();
      YWCE.updateDataTypeLabel();
    } else if (step === 3) {
      YWCE.debug.log("YWCE: Fetching preview data for step 3");
      YWCE.fetchPreviewData();
    } else if (step === 4) {
      YWCE.debug.log("YWCE: Setting up step 4");
      YWCE.setupExportFilters();
      YWCE.updateExportSummary();
      YWCE.setupExportButtonAnimation();

      YWCE.generateExportName();

      const generateButton = document.getElementById("generate-name");
      if (generateButton) {
        const newGenerateButton = generateButton.cloneNode(true);
        generateButton.parentNode.replaceChild(
          newGenerateButton,
          generateButton
        );

        newGenerateButton.addEventListener("click", function () {
          YWCE.debug.log("YWCE: Generate button clicked");
          YWCE.generateExportName();
          YWCE.updateExportSummary();
        });
      } else {
        YWCE.debug.error("YWCE: Generate button not found in step 4");
      }

      jQuery(".export-config-container").hide().fadeIn(400);
    }
  };

  YWCE.updateProgressBar = function (step) {
    const progressBar = document.getElementById("ywce-progress");
    if (progressBar) {
      const percentage = ((step - 1) / 3) * 100;
      progressBar.style.width = `${percentage}%`;
      progressBar.setAttribute("aria-valuenow", percentage);
    }
  };

  YWCE.updateButtons = function (step) {
    YWCE.debug.log("YWCE: Updating buttons for step:", step);

    const prevButton = document.getElementById("ywce-prev-step");
    const nextButton = document.getElementById("ywce-next-step");
    const exportButton = document.getElementById("ywce-export-btn");

    YWCE.state.currentStep = step;

    if (prevButton) {
      if (step === 1) {
        prevButton.style.display = "none";
      } else {
        prevButton.style.display = "inline-block";
        prevButton.disabled = false;
      }
    }

    if (nextButton) {
      if (step < 4) {
        nextButton.style.display = "inline-block";

        if (step === 1) {
          nextButton.disabled = !YWCE.state.selectedDataSource;
        } else if (step === 2) {
          nextButton.disabled =
            YWCE.state.selectedFields.length === 0 &&
            YWCE.state.selectedMeta.length === 0 &&
            YWCE.state.selectedTaxonomies.length === 0;
        } else if (step === 3) {
          nextButton.disabled = false;
        }
      } else {
        nextButton.style.display = "none";
      }
    }

    if (exportButton) {
      if (step === 4) {
        exportButton.style.display = "inline-block";
        exportButton.disabled = false;

        if (YWCE.state.fieldsModifiedAfterExport) {
          exportButton.textContent = YWCE.__("Start Export");
          exportButton.classList.remove("btn-outline-primary");
          exportButton.classList.add("btn-primary");
        }
      } else {
        exportButton.style.display = "none";
      }
    }
  };

  YWCE.initEventListeners = function () {
    YWCE.debug.log("YWCE: Initializing event listeners");

    try {
      document.addEventListener("click", function (event) {
        if (
          event.target &&
          (event.target.id === "generate-name" ||
            event.target.dataset.action === "generate-name")
        ) {
          YWCE.debug.log("YWCE: Generate button clicked via document handler");
          YWCE.generateExportName();
          YWCE.updateExportSummary();
        }
      });

      const debugButton = document.getElementById("ywce-debug-btn");
      if (debugButton) {
        debugButton.addEventListener("click", function () {
          YWCE.debug.log("YWCE: Debug button clicked");

          YWCE.debugState();

          const envInfo = {
            isDevEnvironment: YWCE.debug.isDevEnvironment(),
            hostname: window.location.hostname,
            userAgent: navigator.userAgent,
            screenSize: `${window.innerWidth}x${window.innerHeight}`,
            bootstrapVersion:
              typeof bootstrap !== "undefined"
                ? bootstrap.Tooltip.VERSION
                : "Not loaded",
            jQueryVersion:
              typeof jQuery !== "undefined" ? jQuery.fn.jquery : "Not loaded",
          };

          YWCE.debug.log("YWCE Debug - Environment:", envInfo);

          fetch(
            `${ywce_ajax.ajax_url}?action=ywce_fetch_data_fields&nonce=${ywce_ajax.nonce}&source=order`
          )
            .then((response) => response.json())
            .then((data) => {
              YWCE.debug.log("YWCE Debug - Order fields:", data);

              const debugReport = {
                timestamp: new Date().toISOString(),
                environment: envInfo,
                state: JSON.parse(JSON.stringify(YWCE.state)),
                ajaxTest: data,
              };

              console.table(debugReport.state);

              let debugMessage = YWCE.__("Debug info logged to console");
              if (YWCE.debug.isDebugForced()) {
                debugMessage += "\n\n" + YWCE.__("Debug mode enabled via URL");
                debugMessage +=
                  "\n" + YWCE.__("Debug mode disable instruction");
              } else if (YWCE.debug.isDevEnvironment()) {
                debugMessage += "\n\n" + YWCE.__("Debug mode dev environment");
                debugMessage +=
                  "\n" + YWCE.__("Hostname") + " " + window.location.hostname;
              }

              alert(debugMessage);
            })
            .catch((error) => {
              YWCE.debug.error("YWCE Debug - Error:", error);
              alert("Error: " + error.message);
            });
        });
      } else {
        YWCE.debug.error("YWCE: Debug button not found!");
      }

      const productButton = document.getElementById("btn-product");
      const userButton = document.getElementById("btn-user");
      const orderButton = document.getElementById("btn-order");
      const sourceButtons = [productButton, userButton, orderButton];

      YWCE.debug.log(
        "YWCE: Source cards found:",
        productButton ? "Product ✓" : "Product ✗",
        userButton ? "User ✓" : "User ✗",
        orderButton ? "Order ✓" : "Order ✗"
      );

      const handleSourceButtonClick = function (card) {
        if (!card) {
          YWCE.debug.error("YWCE: Source card not found:", card);
          return;
        }

        card.addEventListener("click", function () {
          const source = this.dataset.source;
          YWCE.debug.log("YWCE: Source card clicked:", source);

          sourceButtons.forEach((btn) => {
            if (btn) btn.classList.remove("active");
          });

          this.classList.add("active");

          YWCE.state.selectedDataSource = source;

          YWCE.state.fieldsModifiedAfterExport = true;

          if (YWCE.elements.nextStepButton) {
            YWCE.elements.nextStepButton.disabled = false;
          }

          YWCE.updateDataTypeLabel();

          YWCE.debug.log(
            "YWCE: Selected data source:",
            YWCE.state.selectedDataSource
          );
        });
      };

      if (productButton) {
        handleSourceButtonClick(productButton);
      } else {
        YWCE.debug.error("YWCE: Product button not found!");
      }

      if (userButton) {
        handleSourceButtonClick(userButton);
      } else {
        YWCE.debug.error("YWCE: User button not found!");
      }

      if (orderButton) {
        handleSourceButtonClick(orderButton);
      } else {
        YWCE.debug.error("YWCE: Order button not found!");
      }

      const nextButton = document.getElementById("ywce-next-step");
      if (nextButton) {
        nextButton.addEventListener("click", function () {
          YWCE.debug.log(
            "YWCE: Next button clicked, current step:",
            YWCE.state.currentStep
          );
          if (YWCE.state.currentStep === 2 && !YWCE.validateRequiredFields()) {
            return;
          }

          YWCE.showStep(YWCE.state.currentStep + 1);
        });
      } else {
        YWCE.debug.error("YWCE: Next button not found!");
      }

      const prevButton = document.getElementById("ywce-prev-step");
      if (prevButton) {
        prevButton.addEventListener("click", function () {
          YWCE.debug.log(
            "YWCE: Previous button clicked, current step:",
            YWCE.state.currentStep
          );

          if (YWCE.state.currentStep === 4) {
            jQuery("#ywce-download-links").html("").hide();
            jQuery("#ywce-export-completed-msg").hide();

            jQuery(".step-4").removeClass("export-complete");
          }

          YWCE.showStep(YWCE.state.currentStep - 1);
        });
      } else {
        YWCE.debug.error("YWCE: Previous button not found!");
      }

      const formatButtons = document.querySelectorAll(
        "#ywce-format-buttons .format-btn"
      );
      if (formatButtons && formatButtons.length > 0) {
        formatButtons.forEach((button) => {
          button.addEventListener("click", function () {
            const format = this.dataset.format;
            YWCE.debug.log("YWCE: Format button clicked:", format);

            this.classList.toggle("selected");

            if (this.classList.contains("selected")) {
              if (!YWCE.state.selectedFormats.includes(format)) {
                YWCE.state.selectedFormats.push(format);
              }
            } else {
              YWCE.state.selectedFormats = YWCE.state.selectedFormats.filter(
                (f) => f !== format
              );
            }

            YWCE.debug.log(
              "YWCE: Selected formats:",
              YWCE.state.selectedFormats
            );

            YWCE.updateExportSummary();
          });

          if (button.dataset.format === "csv") {
            button.classList.add("selected");
          }
        });
      }

      const exportButton = document.getElementById("ywce-export-btn");
      if (exportButton) {
        exportButton.addEventListener("click", function () {
          YWCE.startExport();
        });
      }
    } catch (error) {
      YWCE.debug.error("YWCE: Error initializing event listeners:", error);
    }
  };

  YWCE.resetSelectionsAndPreview = function () {
    YWCE.state.selectedFields = [];
    YWCE.state.selectedMeta = [];
    YWCE.state.selectedTaxonomies = [];

    YWCE.clearSelectOptions("#ywce-data-fields");
    YWCE.clearSelectOptions("#ywce-meta-fields");
    YWCE.clearSelectOptions("#ywce-taxonomy-fields");

    if (YWCE.state.selectedDataSource === "user") {
      document.querySelector("#ywce-taxonomy-container").style.display = "none";
    }

    YWCE.clearPreviewTable();
  };

  YWCE.clearSelectOptions = function (selector) {
    const select = document.querySelector(selector);
    if (select) select.innerHTML = "";
  };

  YWCE.clearPreviewTable = function () {
    document.querySelector("#ywce-wizard .step-3 table thead tr").innerHTML =
      "";
    document.querySelector("#ywce-wizard .step-3 table tbody").innerHTML = "";
  };

  YWCE.handleDragStart = function (event) {
    YWCE.state.draggingColumn = event.target.closest("th").dataset.key;
    event.dataTransfer.effectAllowed = "move";
    event.dataTransfer.setData("text/plain", YWCE.state.draggingColumn);
  };

  YWCE.handleDragOver = function (event) {
    event.preventDefault();

    let targetTh = event.target.closest("th");
    if (!targetTh || targetTh.dataset.key === YWCE.state.draggingColumn) return;

    targetTh.classList.add("drag-over");
  };

  YWCE.handleDragLeave = function (event) {
    let targetTh = event.target.closest("th");
    if (!targetTh) return;

    targetTh.classList.remove("drag-over");
  };

  YWCE.handleDrop = function (event) {
    event.preventDefault();
    let targetKey = event.target.closest("th").dataset.key;
    let fromIndex = YWCE.state.selectedColumns.indexOf(
      YWCE.state.draggingColumn
    );
    let toIndex = YWCE.state.selectedColumns.indexOf(targetKey);

    if (fromIndex !== -1 && toIndex !== -1 && fromIndex !== toIndex) {
      YWCE.state.selectedColumns.splice(
        toIndex,
        0,
        YWCE.state.selectedColumns.splice(fromIndex, 1)[0]
      );
      YWCE.renderPreview(YWCE.state.lastPreviewData);
    }

    document
      .querySelectorAll(".drag-over")
      .forEach((el) => el.classList.remove("drag-over"));
  };

  YWCE.handleRenameColumn = function (key, thElement) {
    if (thElement.hasAttribute("data-editing")) {
      return;
    }

    thElement.setAttribute("data-editing", "true");

    const currentName = YWCE.state.selectedHeaders[key] || key;

    const modal = document.createElement("div");
    modal.className = "ywce-modal";
    modal.innerHTML = `
            <div class="ywce-modal-content">
                <h3>${YWCE.__("Edit Column Name")}</h3>
                <p>${YWCE.__('Enter a new name for the "%s" column:').replace(
                  "%s",
                  currentName
                )}</p>
                <input type="text" id="ywce-rename-input" class="form-control" value="${currentName}">
                <div class="ywce-modal-actions mt-3">
                    <button id="ywce-rename-cancel" class="btn btn-outline-secondary">${YWCE.__(
                      "Cancel"
                    )}</button>
                    <button id="ywce-rename-save" class="btn btn-primary">${YWCE.__(
                      "Save"
                    )}</button>
                </div>
            </div>
        `;

    document.body.appendChild(modal);

    const input = document.getElementById("ywce-rename-input");
    input.focus();
    input.select();

    document
      .getElementById("ywce-rename-save")
      .addEventListener("click", function () {
        const newName = input.value.trim();
        if (newName !== "") {
          YWCE.state.selectedHeaders[key] = newName;

          thElement.querySelector(".column-title").textContent = newName;

          YWCE.debug.log("YWCE: Updated headers:", YWCE.state.selectedHeaders);
        }

        document.body.removeChild(modal);
        thElement.removeAttribute("data-editing");
      });

    document
      .getElementById("ywce-rename-cancel")
      .addEventListener("click", function () {
        document.body.removeChild(modal);
        thElement.removeAttribute("data-editing");
      });

    input.addEventListener("keyup", function (event) {
      if (event.key === "Enter") {
        document.getElementById("ywce-rename-save").click();
      } else if (event.key === "Escape") {
        document.getElementById("ywce-rename-cancel").click();
      }
    });
  };

  YWCE.fetchDataFields = function () {
    YWCE.debug.log(
      "YWCE: Fetching data fields for source:",
      YWCE.state.selectedDataSource
    );

    if (!YWCE.state.selectedDataSource) {
      YWCE.showWarningModal("Please select a data source first.");
      return;
    }

    const dataFieldsContainer = document.getElementById(
      "ywce-data-fields-container"
    );
    const metaFieldsContainer = document.getElementById(
      "ywce-meta-fields-container"
    );
    const taxonomyFieldsContainer = document.getElementById(
      "ywce-taxonomy-fields-container"
    );

    dataFieldsContainer.innerHTML =
      '<div class="p-3 text-center text-muted">' +
      YWCE.__("Loading fields...") +
      "</div>";
    metaFieldsContainer.innerHTML =
      '<div class="p-3 text-center text-muted">' +
      YWCE.__("Loading meta fields...") +
      "</div>";
    taxonomyFieldsContainer.innerHTML =
      '<div class="p-3 text-center text-muted">' +
      YWCE.__("Loading taxonomy fields...") +
      "</div>";

    fetch(
      `${ywce_ajax.ajax_url}?action=ywce_fetch_data_fields&nonce=${ywce_ajax.nonce}&source=${YWCE.state.selectedDataSource}`
    )
      .then((response) => {
        if (!response.ok) {
          throw new Error(
            YWCE.__("HTTP error! Status:") + ` ${response.status}`
          );
        }
        return response.json().catch((error) => {
          YWCE.debug.log("YWCE: JSON parse error:", error);
          throw new Error(YWCE.__("Failed to parse JSON response"));
        });
      })
      .then((data) => {
        YWCE.debug.log("YWCE: Data fields received:", data);

        YWCE.debug.log(
          "YWCE: data.fields type:",
          Array.isArray(data.fields) ? "Array" : typeof data.fields
        );
        YWCE.debug.log(
          "YWCE: data.meta type:",
          Array.isArray(data.meta) ? "Array" : typeof data.meta
        );
        YWCE.debug.log(
          "YWCE: data.taxonomies type:",
          Array.isArray(data.taxonomies) ? "Array" : typeof data.taxonomies
        );
        YWCE.debug.log(
          "YWCE: data.required type:",
          Array.isArray(data.required) ? "Array" : typeof data.required
        );

        if (!data.fields) {
          YWCE.showWarningModal("Failed to load fields.");
          return;
        }

        YWCE.state.selectedFields = [];
        YWCE.state.selectedMeta = [];
        YWCE.state.selectedTaxonomies = [];

        const fieldsArray = Array.isArray(data.fields)
          ? data.fields
          : Object.values(data.fields);
        YWCE.state.hasVariableProducts = fieldsArray.includes("Parent ID");

        YWCE.populateFieldList(
          "ywce-data-fields-container",
          "ywce-data-fields",
          fieldsArray
        );

        const metaArray = Array.isArray(data.meta)
          ? data.meta
          : data.meta
          ? Object.values(data.meta)
          : [];
        YWCE.populateFieldList(
          "ywce-meta-fields-container",
          "ywce-meta-fields",
          metaArray
        );

        if (data.taxonomies && YWCE.state.selectedDataSource === "product") {
          const taxonomiesArray = Array.isArray(data.taxonomies)
            ? data.taxonomies
            : Object.values(data.taxonomies);
          document.querySelector("#ywce-taxonomy-container").style.display =
            taxonomiesArray.length > 0 ? "block" : "none";
          YWCE.populateFieldList(
            "ywce-taxonomy-fields-container",
            "ywce-taxonomy-fields",
            taxonomiesArray
          );
        } else {
          document.querySelector("#ywce-taxonomy-container").style.display =
            "none";
        }

        if (data.required && data.required.length > 0) {
          const requiredArray = Array.isArray(data.required)
            ? data.required
            : Object.values(data.required);

          requiredArray.forEach((field) => {
            const fieldItem = document.querySelector(
              `.field-item[data-value="${field}"]`
            );
            const option = document.querySelector(
              `#ywce-data-fields option[value="${field}"]`
            );

            if (fieldItem) {
              fieldItem.classList.add("selected");
              fieldItem.classList.add("required");

              const requiredNote = document.createElement("span");
              requiredNote.className = "required-note";
              requiredNote.textContent = YWCE.__("(required)");
              fieldItem.appendChild(requiredNote);
            }

            if (option) {
              option.selected = true;
              YWCE.state.selectedFields.push(field);
            }
          });
        }

        YWCE.updateNextButtonState();
      })
      .catch((error) => {
        YWCE.debug.log("YWCE: Error fetching data fields:", error);

        dataFieldsContainer.innerHTML =
          '<div class="p-3 text-center text-danger">' +
          YWCE.__(
            "Error loading fields. Please try again or contact support."
          ) +
          "</div>";
        metaFieldsContainer.innerHTML =
          '<div class="p-3 text-center text-danger">' +
          YWCE.__("Error loading meta fields.") +
          "</div>";
        taxonomyFieldsContainer.innerHTML =
          '<div class="p-3 text-center text-danger">' +
          YWCE.__("Error loading taxonomy fields.") +
          "</div>";

        YWCE.showWarningModal("Error fetching data fields: " + error.message);
      });
  };

  YWCE.updateSelectOptions = function (selectId, options) {
    const select = document.getElementById(selectId);
    const containerId = selectId.replace("ywce-", "ywce-") + "-container";

    if (!select || !options) {
      YWCE.debug.error("YWCE: Select element or options not found");
      return;
    }

    YWCE.populateFieldList(containerId, selectId, options);
  };

  YWCE.populateFieldList = function (containerId, selectId, fields) {
    const container = document.getElementById(containerId);
    const select = document.getElementById(selectId);

    if (!container || !select) return;

    const fieldsArray = fields
      ? Array.isArray(fields)
        ? fields
        : Object.values(fields)
      : [];

    container.innerHTML = "";

    select.innerHTML = "";

    if (fieldsArray.length === 0) {
      container.innerHTML =
        '<div class="p-3 text-center text-muted">' +
        YWCE.__("No fields available") +
        "</div>";
      return;
    }

    fieldsArray.forEach((field) => {
      if (!field) return;

      const fieldItem = document.createElement("div");
      fieldItem.className = "field-item";
      fieldItem.dataset.value = field;
      fieldItem.textContent = field;

      container.appendChild(fieldItem);

      const option = document.createElement("option");
      option.value = field;
      option.textContent = field;
      select.appendChild(option);
    });

    YWCE.setupFieldItemSelection();
  };

  YWCE.showWarningModal = function (message) {
    document
      .querySelectorAll("#ywce-warning-modal")
      .forEach((modal) => modal.remove());

    if (typeof bootstrap !== "undefined") {
      const modalHtml = `
            <div class="modal fade" id="ywce-warning-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${YWCE.__(
                              "Export Requirement"
                            )}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="${YWCE.__(
                              "Close"
                            )}"></button>
                        </div>
                        <div class="modal-body">
                            <p>${message}</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${YWCE.__(
                              "OK"
                            )}</button>
                        </div>
                    </div>
                </div>
            </div>`;

      document.body.insertAdjacentHTML("beforeend", modalHtml);

      const modalElement = document.getElementById("ywce-warning-modal");
      const modal = new bootstrap.Modal(modalElement);
      modal.show();
    } else {
      alert(message);
    }
  };

  YWCE.getSelectedValues = function (selector) {
    return Array.from(document.querySelector(selector).selectedOptions).map(
      (option) => option.value
    );
  };

  YWCE.fetchPreviewData = function () {
    YWCE.debug.log("YWCE: Fetching preview data...");

    const dataFieldsSelect = document.getElementById("ywce-data-fields");
    const metaFieldsSelect = document.getElementById("ywce-meta-fields");
    const taxonomyFieldsSelect = document.getElementById(
      "ywce-taxonomy-fields"
    );

    if (dataFieldsSelect) {
      YWCE.state.selectedFields = Array.from(
        dataFieldsSelect.selectedOptions
      ).map((option) => option.value);
    }

    if (metaFieldsSelect) {
      YWCE.state.selectedMeta = Array.from(
        metaFieldsSelect.selectedOptions
      ).map((option) => option.value);
    }

    if (taxonomyFieldsSelect) {
      YWCE.state.selectedTaxonomies = Array.from(
        taxonomyFieldsSelect.selectedOptions
      ).map((option) => option.value);
    }

    YWCE.debug.log("YWCE: Selected fields:", YWCE.state.selectedFields);
    YWCE.debug.log("YWCE: Selected meta:", YWCE.state.selectedMeta);
    YWCE.debug.log("YWCE: Selected taxonomies:", YWCE.state.selectedTaxonomies);

    if (
      YWCE.state.selectedFields.length === 0 &&
      YWCE.state.selectedMeta.length === 0 &&
      YWCE.state.selectedTaxonomies.length === 0
    ) {
      YWCE.showWarningModal(
        "Please select at least one field, meta field, or taxonomy before proceeding."
      );
      return;
    }

    YWCE.clearPreviewTable();

    let params = new URLSearchParams({
      action: "ywce_fetch_preview_data",
      nonce: ywce_ajax.nonce,
      source: YWCE.state.selectedDataSource,
      fields: YWCE.state.selectedFields.join(","),
      meta: YWCE.state.selectedMeta.join(","),
      taxonomies: YWCE.state.selectedTaxonomies.join(","),
    });

    const previewTable = document.querySelector(
      "#ywce-wizard .step-3 table tbody"
    );
    const tableWrapper = document.querySelector("#ywce-preview-table-wrapper");
    if (tableWrapper) {
      tableWrapper.classList.add("loading");
      tableWrapper.setAttribute(
        "data-loading-text",
        YWCE.__("Loading preview data...")
      );
    }

    fetch(`${ywce_ajax.ajax_url}?${params.toString()}`)
      .then((response) => {
        if (!response.ok) {
          throw new Error(
            YWCE.__("HTTP error! Status:") + ` ${response.status}`
          );
        }
        return response.json();
      })
      .then((data) => {
        if (tableWrapper) {
          tableWrapper.classList.remove("loading");
        }

        YWCE.debug.log("YWCE: Preview data received:", data);

        if (!data || !data.data || data.data.length === 0) {
          YWCE.showWarningModal(
            YWCE.__("No data found. Please check your selection.")
          );
          return;
        }

        YWCE.state.selectedColumns = [
          ...YWCE.state.selectedFields,
          ...YWCE.state.selectedMeta,
          ...YWCE.state.selectedTaxonomies,
        ];

        YWCE.state.lastPreviewData = data.data;
        YWCE.renderPreview(YWCE.state.lastPreviewData);

        const nextButton = document.getElementById("ywce-next-step");
        if (nextButton) {
          nextButton.disabled = false;
        }
      })
      .catch((error) => {
        YWCE.debug.error("Error fetching preview data:", error);
        YWCE.showWarningModal(
          YWCE.__("An error occurred while fetching preview data.")
        );
      });
  };

  YWCE.renderPreview = function (previewData) {
    YWCE.debug.log("YWCE: Rendering preview data:", previewData);

    if (!previewData || !Array.isArray(previewData)) {
      YWCE.debug.error("Error: previewData is undefined or not an array.");
      return;
    }

    if (previewData.length === 0) {
      YWCE.debug.error("Error: previewData is empty.");
      return;
    }

    YWCE.state.lastPreviewData = previewData;

    const tableHead = document.querySelector(
      "#ywce-wizard .step-3 table thead tr"
    );
    const tableBody = document.querySelector(
      "#ywce-wizard .step-3 table tbody"
    );

    if (!tableHead || !tableBody) {
      YWCE.debug.error("Error: table elements not found.");
      return;
    }

    tableHead.innerHTML = "";
    tableBody.innerHTML = "";

    if (
      !YWCE.state.selectedColumns ||
      YWCE.state.selectedColumns.length === 0
    ) {
      YWCE.state.selectedColumns = [
        ...YWCE.state.selectedFields,
        ...YWCE.state.selectedMeta,
        ...YWCE.state.selectedTaxonomies,
      ];
    }

    YWCE.debug.log("YWCE: Selected columns:", YWCE.state.selectedColumns);

    YWCE.state.selectedColumns.forEach((key) => {
      const th = document.createElement("th");
      th.className = "draggable-header";
      th.dataset.key = key;

      const displayName = YWCE.state.selectedHeaders[key] || key;

      th.innerHTML = `
            <div class="header-content">
                <span class="reorder-icon" draggable="true">☰</span>
                <span class="column-title">${displayName}</span>
                <span class="edit-icon" title="${YWCE.__(
                  "Edit column name"
                )}">✏️</span>
            </div>
            `;

      const reorderIcon = th.querySelector(".reorder-icon");
      reorderIcon.addEventListener("dragstart", function (event) {
        YWCE.handleDragStart(event);
        setTimeout(() => {
          th.classList.add("dragging");
        }, 0);
      });

      reorderIcon.addEventListener("dragend", function () {
        th.classList.remove("dragging");
        document
          .querySelectorAll(".drag-over")
          .forEach((el) => el.classList.remove("drag-over"));
      });

      th.addEventListener("dragover", YWCE.handleDragOver);
      th.addEventListener("dragleave", YWCE.handleDragLeave);
      th.addEventListener("drop", YWCE.handleDrop);

      th.querySelector(".edit-icon").addEventListener("click", () =>
        YWCE.handleRenameColumn(key, th)
      );

      tableHead.appendChild(th);
    });

    previewData.forEach((row) => {
      const tr = document.createElement("tr");

      YWCE.state.selectedColumns.forEach((key) => {
        const td = document.createElement("td");
        let value = row[key];

        if (value === undefined || value === null) {
          value = "";
        } else if (typeof value === "object") {
          try {
            value = JSON.stringify(value);
          } catch (e) {
            value = YWCE.__("Object placeholder");
          }
        }

        td.textContent = value;
        tr.appendChild(td);
      });

      tableBody.appendChild(tr);
    });
  };

  YWCE.validateRequiredFields = function () {
    YWCE.state.selectedFields = YWCE.getSelectedValues("#ywce-data-fields");

    let missingFields = [];

    if (!YWCE.state.selectedFields.includes("ID")) {
      missingFields.push(YWCE.__("ID field required"));
    }

    if (
      YWCE.state.hasVariableProducts &&
      !YWCE.state.selectedFields.includes("Parent ID")
    ) {
      missingFields.push(YWCE.__("Parent ID field required"));
    }

    if (missingFields.length > 0) {
      YWCE.showWarningModal(missingFields.join("<br>"));
      return false;
    }

    return true;
  };

  YWCE.setupExportFilters = function () {
    jQuery(".filter-section").hide();

    jQuery("#user-custom-date-range").hide();
    jQuery("#order-custom-date-range").hide();

    var selectedSource = YWCE.state.selectedDataSource;

    if (selectedSource === "product") {
      jQuery("#product-filters").show();

      jQuery.ajax({
        url: ywce_ajax.ajax_url,
        type: "POST",
        data: {
          action: "ywce_fetch_product_types",
          nonce: ywce_ajax.nonce,
        },
        success: function (response) {
          if (response.success && response.data.product_types) {
            var $container = jQuery("#product-type").prev(
              ".field-list-container"
            );

            $container.find('.field-item:not([data-value="all"])').remove();

            response.data.product_types.forEach(function (type) {
              $container.append(
                '<div class="field-item" data-value="' +
                  type.value +
                  '">' +
                  type.label +
                  "</div>"
              );
            });

            $container
              .find('.field-item[data-value="all"]')
              .addClass("selected");

            YWCE.setupFilterItemSelection();
          }
        },
      });
    } else if (selectedSource === "user") {
      jQuery("#user-filters").show();

      jQuery.ajax({
        url: ywce_ajax.ajax_url,
        type: "POST",
        data: {
          action: "ywce_fetch_user_roles",
          nonce: ywce_ajax.nonce,
        },
        success: function (response) {
          if (response.success && response.data.user_roles) {
            var $container = jQuery("#user-role").prev(".field-list-container");

            $container.find('.field-item:not([data-value="all"])').remove();

            response.data.user_roles.forEach(function (role) {
              $container.append(
                '<div class="field-item" data-value="' +
                  role.value +
                  '">' +
                  role.label +
                  "</div>"
              );
            });

            $container
              .find('.field-item[data-value="all"]')
              .addClass("selected");

            YWCE.setupFilterItemSelection();
          }
        },
      });
    } else if (selectedSource === "order") {
      jQuery("#order-filters").show();

      jQuery.ajax({
        url: ywce_ajax.ajax_url,
        type: "POST",
        data: {
          action: "ywce_fetch_order_statuses",
          nonce: ywce_ajax.nonce,
        },
        success: function (response) {
          if (response.success && response.data.order_statuses) {
            var $container = jQuery("#order-status").prev(
              ".field-list-container"
            );

            $container.find('.field-item:not([data-value="all"])').remove();

            response.data.order_statuses.forEach(function (status) {
              $container.append(
                '<div class="field-item" data-value="' +
                  status.value +
                  '">' +
                  status.label +
                  "</div>"
              );
            });

            $container
              .find('.field-item[data-value="all"]')
              .addClass("selected");

            YWCE.setupFilterItemSelection();
          }
        },
      });
    }

    YWCE.setupDateRangeListeners();

    YWCE.generateExportName();

    YWCE.updateExportSummary();

    jQuery(".format-btn").first().addClass("selected");
  };

  YWCE.setupFilterItemSelection = function () {
    YWCE.debug.log("YWCE: Setting up filter item selection for step 4");

    var filterContainers = jQuery(".filter-section .field-list-container");

    filterContainers.each(function () {
      var $container = jQuery(this);
      var selectId = $container.next("select").attr("id");

      YWCE.debug.log("YWCE: Setting up filter container for select:", selectId);

      var $selectedItems = $container.find(".field-item.selected");
      if ($selectedItems.length === 0) {
        $container.find('.field-item[data-value="all"]').addClass("selected");
      }

      $container
        .find(".field-item")
        .off("click")
        .on("click", function () {
          var $this = jQuery(this);
          var $select = jQuery("#" + selectId);
          var value = $this.data("value");

          YWCE.debug.log(
            "YWCE: Filter item clicked:",
            value,
            "for select:",
            selectId
          );

          if (value === "all") {
            $container.find(".field-item").removeClass("selected");
            $this.addClass("selected");
            $select.val([]);
          } else {
            $container
              .find('.field-item[data-value="all"]')
              .removeClass("selected");

            $this.toggleClass("selected");

            var selectedValues = [];
            $container.find(".field-item.selected").each(function () {
              if (jQuery(this).data("value") !== "all") {
                selectedValues.push(jQuery(this).data("value"));
              }
            });

            $select.val(selectedValues);

            if (selectedValues.length === 0) {
              $container
                .find('.field-item[data-value="all"]')
                .addClass("selected");
            }
          }

          YWCE.updateExportSummary();
        });
    });

    jQuery(".filter-section .select-all-btn")
      .off("click")
      .on("click", function () {
        var targetId = jQuery(this).data("target");
        var $container = jQuery("#" + targetId);
        var $select = jQuery("#" + $container.next("select").attr("id"));

        YWCE.debug.log(
          "YWCE: Filter Select All clicked for container:",
          targetId
        );

        $container.find(".field-item").each(function () {
          if (jQuery(this).data("value") !== "all") {
            jQuery(this).addClass("selected");
          } else {
            jQuery(this).removeClass("selected");
          }
        });

        var selectedValues = [];
        $container.find(".field-item.selected").each(function () {
          if (jQuery(this).data("value") !== "all") {
            selectedValues.push(jQuery(this).data("value"));
          }
        });

        $select.val(selectedValues);

        YWCE.updateExportSummary();
      });

    jQuery(".filter-section .deselect-all-btn")
      .off("click")
      .on("click", function () {
        var targetId = jQuery(this).data("target");
        var $container = jQuery("#" + targetId);
        var $select = jQuery("#" + $container.next("select").attr("id"));

        YWCE.debug.log(
          "YWCE: Filter Deselect All clicked for container:",
          targetId
        );

        $container.find(".field-item").removeClass("selected");
        $container.find('.field-item[data-value="all"]').addClass("selected");

        $select.val([]);

        YWCE.updateExportSummary();
      });

    YWCE.updateExportSummary();
  };

  YWCE.setupDateRangeListeners = function () {
    jQuery("#user-date-range").on("change", function () {
      var selectedRange = jQuery(this).val();
      var customDateContainer = jQuery("#user-custom-date-range");

      if (selectedRange === "custom") {
        customDateContainer.slideDown(300);

        if (
          !jQuery("#user-date-from").val() ||
          !jQuery("#user-date-to").val()
        ) {
          var today = new Date();
          var thirtyDaysAgo = new Date(today);
          thirtyDaysAgo.setDate(today.getDate() - 30);

          jQuery("#user-date-from").val(YWCE.formatDate(thirtyDaysAgo));
          jQuery("#user-date-to").val(YWCE.formatDate(today));
        }
      } else {
        customDateContainer.slideUp(300);
        if (selectedRange === "all") {
          jQuery("#user-date-from, #user-date-to").val("");
        }
      }
      YWCE.updateExportSummary();
    });

    jQuery("#order-date-range").on("change", function () {
      var selectedRange = jQuery(this).val();
      var customDateContainer = jQuery("#order-custom-date-range");

      if (selectedRange === "custom") {
        customDateContainer.slideDown(300);

        if (
          !jQuery("#order-date-from").val() ||
          !jQuery("#order-date-to").val()
        ) {
          var today = new Date();
          var thirtyDaysAgo = new Date(today);
          thirtyDaysAgo.setDate(today.getDate() - 30);

          jQuery("#order-date-from").val(YWCE.formatDate(thirtyDaysAgo));
          jQuery("#order-date-to").val(YWCE.formatDate(today));
        }
      } else {
        customDateContainer.slideUp(300);
        if (selectedRange === "all") {
          jQuery("#order-date-from, #order-date-to").val("");
        }
      }
      YWCE.updateExportSummary();
    });

    jQuery(
      "#user-date-from, #user-date-to, #order-date-from, #order-date-to"
    ).on("change", function () {
      var $this = jQuery(this);
      var dateValue = $this.val();

      if (dateValue && !YWCE.isValidDate(dateValue)) {
        YWCE.showWarningModal(
          YWCE.__("Please enter a valid date in YYYY-MM-DD format")
        );
        $this.val("");
        return;
      }

      var isUserDate = $this.attr("id").startsWith("user-date");
      var fromDate = jQuery(
        isUserDate ? "#user-date-from" : "#order-date-from"
      ).val();
      var toDate = jQuery(
        isUserDate ? "#user-date-to" : "#order-date-to"
      ).val();

      if (fromDate && toDate) {
        if (new Date(fromDate) > new Date(toDate)) {
          YWCE.showWarningModal(
            YWCE.__("From date cannot be later than To date")
          );
          $this.val("");
          return;
        }
      }

      YWCE.updateExportSummary();
    });

    jQuery("#user-custom-date-range, #order-custom-date-range").hide();
  };

  YWCE.isValidDate = function (dateString) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
      return false;
    }

    var parts = dateString.split("-");
    var year = parseInt(parts[0], 10);
    var month = parseInt(parts[1], 10);
    var day = parseInt(parts[2], 10);

    if (year < 1000 || year > 3000 || month == 0 || month > 12) {
      return false;
    }

    var monthLength = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    if (year % 400 == 0 || (year % 100 != 0 && year % 4 == 0)) {
      monthLength[1] = 29;
    }

    return day > 0 && day <= monthLength[month - 1];
  };

  YWCE.formatDate = function (date) {
    var year = date.getFullYear();
    var month = ("0" + (date.getMonth() + 1)).slice(-2);
    var day = ("0" + date.getDate()).slice(-2);
    return year + "-" + month + "-" + day;
  };

  YWCE.generateExportName = function () {
    YWCE.debug.log("YWCE: Generating export name");
    var selectedSource = YWCE.state.selectedDataSource;
    var date = new Date();
    var timestamp = Date.now();
    var formattedDate =
      date.getFullYear() +
      "-" +
      ("0" + (date.getMonth() + 1)).slice(-2) +
      "-" +
      ("0" + date.getDate()).slice(-2) +
      "-" +
      ("0" + date.getHours()).slice(-2) +
      ("0" + date.getMinutes()).slice(-2);

    var exportName = "";

    if (selectedSource === "product") {
      exportName =
        "products_export_" +
        formattedDate +
        "_" +
        timestamp.toString().slice(-4);
    } else if (selectedSource === "user") {
      exportName =
        "users_export_" + formattedDate + "_" + timestamp.toString().slice(-4);
    } else if (selectedSource === "order") {
      exportName =
        "orders_export_" + formattedDate + "_" + timestamp.toString().slice(-4);
    } else {
      exportName =
        "woocommerce_export_" +
        formattedDate +
        "_" +
        timestamp.toString().slice(-4);
    }

    YWCE.debug.log("YWCE: Generated export name:", exportName);
    jQuery("#export-name").val(exportName);
    return exportName;
  };

  YWCE.updateExportSummary = function () {
    var summaryContent = jQuery("#ywce-export-summary-content");
    var selectedSource = YWCE.state.selectedDataSource;
    var selectedFormats = YWCE.state.selectedFormats;
    var exportName = jQuery("#export-name").val();

    if (!selectedSource || selectedFormats.length === 0 || !exportName) {
      summaryContent.html(
        '<p class="text-muted fst-italic">' +
          YWCE.__("Please complete the export configuration to see a summary") +
          "</p>"
      );
      return;
    }

    var summary = '<ul class="mb-0">';

    summary +=
      "<li><strong>" +
      YWCE.__("Export Name:") +
      "</strong> " +
      exportName +
      "</li>";

    var formatsText = selectedFormats
      .map(function (format) {
        return format.toUpperCase();
      })
      .join(", ");
    summary +=
      "<li><strong>" +
      (selectedFormats.length > 1 ? YWCE.__("Formats:") : YWCE.__("Format:")) +
      "</strong> " +
      formatsText +
      "</li>";

    var sourceTypeText = "";
    if (selectedSource === "product") {
      sourceTypeText = YWCE.__("Products");
    } else if (selectedSource === "user") {
      sourceTypeText = YWCE.__("Users");
    } else if (selectedSource === "order") {
      sourceTypeText = YWCE.__("Orders");
    }
    summary +=
      "<li><strong>" +
      YWCE.__("Data Source:") +
      "</strong> " +
      sourceTypeText +
      "</li>";

    if (selectedSource === "product") {
      var $productTypeContainer = jQuery("#product-type").prev(
        ".field-list-container"
      );
      var $selectedProductTypes = $productTypeContainer.find(
        ".field-item.selected"
      );

      if (
        $selectedProductTypes.length === 1 &&
        $selectedProductTypes.data("value") === "all"
      ) {
        summary +=
          "<li><strong>" +
          YWCE.__("Product Types:") +
          "</strong> " +
          YWCE.__("All") +
          "</li>";
      } else {
        var productTypeLabels = [];
        $selectedProductTypes.each(function () {
          productTypeLabels.push(jQuery(this).text());
        });
        summary +=
          "<li><strong>" +
          YWCE.__("Product Types:") +
          "</strong> " +
          productTypeLabels.join(", ") +
          "</li>";
      }

      var $productStatusContainer = jQuery("#product-status").prev(
        ".field-list-container"
      );
      var $selectedProductStatuses = $productStatusContainer.find(
        ".field-item.selected"
      );

      if (
        $selectedProductStatuses.length === 1 &&
        $selectedProductStatuses.data("value") === "all"
      ) {
        summary +=
          "<li><strong>" +
          YWCE.__("Product Statuses:") +
          "</strong> " +
          YWCE.__("All") +
          "</li>";
      } else {
        var productStatusLabels = [];
        $selectedProductStatuses.each(function () {
          productStatusLabels.push(jQuery(this).text());
        });
        summary +=
          "<li><strong>" +
          YWCE.__("Product Statuses:") +
          "</strong> " +
          productStatusLabels.join(", ") +
          "</li>";
      }
    } else if (selectedSource === "user") {
      // User Roles
      var $userRoleContainer = jQuery("#user-role").prev(
        ".field-list-container"
      );
      var $selectedUserRoles = $userRoleContainer.find(".field-item.selected");

      if (
        $selectedUserRoles.length === 0 ||
        ($selectedUserRoles.length === 1 &&
          $selectedUserRoles.data("value") === "all")
      ) {
        summary +=
          "<li><strong>" +
          YWCE.__("User Roles:") +
          "</strong> " +
          YWCE.__("All") +
          "</li>";
      } else {
        var userRoleLabels = [];
        $selectedUserRoles.each(function () {
          userRoleLabels.push(jQuery(this).text());
        });
        summary +=
          "<li><strong>" +
          YWCE.__("User Roles:") +
          "</strong> " +
          userRoleLabels.join(", ") +
          "</li>";
      }

      var userDateRange = jQuery("#user-date-range").val();
      var userDateRangeText = jQuery("#user-date-range option:selected").text();

      if (userDateRange === "custom") {
        var userDateFrom = jQuery("#user-date-from").val();
        var userDateTo = jQuery("#user-date-to").val();

        if (userDateFrom && userDateTo) {
          summary +=
            "<li><strong>" +
            YWCE.__("Registration Date Range:") +
            "</strong> " +
            userDateFrom +
            " " +
            YWCE.__("to") +
            " " +
            userDateTo +
            "</li>";
        } else {
          summary +=
            "<li><strong>" +
            YWCE.__("Registration Date Range:") +
            "</strong> " +
            YWCE.__("Custom (incomplete)") +
            "</li>";
        }
      } else {
        summary +=
          "<li><strong>" +
          YWCE.__("Registration Date Range") +
          ":</strong> " +
          userDateRangeText +
          "</li>";
      }
    } else if (selectedSource === "order") {
      var $orderStatusContainer = jQuery("#order-status").prev(
        ".field-list-container"
      );
      var $selectedOrderStatuses = $orderStatusContainer.find(
        ".field-item.selected"
      );

      if (
        $selectedOrderStatuses.length === 0 ||
        ($selectedOrderStatuses.length === 1 &&
          $selectedOrderStatuses.data("value") === "all")
      ) {
        summary +=
          "<li><strong>" +
          YWCE.__("Order Statuses:") +
          "</strong> " +
          YWCE.__("All") +
          "</li>";
      } else {
        var orderStatusLabels = [];
        $selectedOrderStatuses.each(function () {
          var label = jQuery(this)
            .clone()
            .children()
            .remove()
            .end()
            .text()
            .trim();
          orderStatusLabels.push(label);
        });
        summary +=
          "<li><strong>" +
          YWCE.__("Order Statuses:") +
          "</strong> " +
          orderStatusLabels.join(", ") +
          "</li>";
      }

      var orderDateRange = jQuery("#order-date-range").val();
      var orderDateRangeText = jQuery(
        "#order-date-range option:selected"
      ).text();

      if (orderDateRange === "custom") {
        var orderDateFrom = jQuery("#order-date-from").val();
        var orderDateTo = jQuery("#order-date-to").val();

        if (orderDateFrom && orderDateTo) {
          summary +=
            "<li><strong>" +
            YWCE.__("Order Date Range:") +
            "</strong> " +
            orderDateFrom +
            " " +
            YWCE.__("to") +
            " " +
            orderDateTo +
            "</li>";
        } else {
          summary +=
            "<li><strong>" +
            YWCE.__("Order Date Range:") +
            "</strong> " +
            YWCE.__("Custom (incomplete)") +
            "</li>";
        }
      } else {
        summary +=
          "<li><strong>" +
          YWCE.__("Order Date Range:") +
          "</strong> " +
          orderDateRangeText +
          "</li>";
      }
    }

    var selectedFields = YWCE.state.selectedFields || [];
    var selectedMeta = YWCE.state.selectedMeta || [];
    var selectedTaxonomies = YWCE.state.selectedTaxonomies || [];

    var totalFields =
      selectedFields.length + selectedMeta.length + selectedTaxonomies.length;

    if (totalFields > 0) {
      summary +=
        "<li><strong>" +
        YWCE.__("Selected Fields:") +
        "</strong> " +
        totalFields +
        " " +
        YWCE.__("fields") +
        "</li>";
    } else {
      summary +=
        "<li><strong>" +
        YWCE.__("Selected Fields:") +
        "</strong> " +
        YWCE.__("All available fields") +
        "</li>";
    }

    summary += "</ul>";

    summaryContent.html(summary);
  };

  YWCE.validateDateRange = function (dateRange, dateFrom, dateTo) {
    if (dateRange === "custom") {
      if (!dateFrom || !dateTo) {
        return false;
      }

      if (!YWCE.isValidDate(dateFrom) || !YWCE.isValidDate(dateTo)) {
        return false;
      }

      var fromDate = new Date(dateFrom);
      var toDate = new Date(dateTo);

      if (isNaN(fromDate.getTime()) || isNaN(toDate.getTime())) {
        return false;
      }

      if (fromDate > toDate) {
        return false;
      }
    }
    return true;
  };

  function validateProductDateRange() {
    const exportMode = jQuery("#product-export-mode").val();

    if (exportMode !== "modified" && exportMode !== "new") {
      return true;
    }

    const dateRange = jQuery("#product-date-range").val();

    if (dateRange !== "custom") {
      return true;
    }

    const fromDate = jQuery("#product-date-from").val();
    const toDate = jQuery("#product-date-to").val();

    if (!fromDate || !toDate) {
      alert(YWCE.__("Please enter a valid date in YYYY-MM-DD format"));
      return false;
    }

    const from = new Date(fromDate);
    const to = new Date(toDate);

    if (from > to) {
      alert(YWCE.__("From date cannot be later than To date"));
      return false;
    }

    return true;
  }

  window.YWCE = window.YWCE || {};
  YWCE.validateProductDateRange = validateProductDateRange;

  YWCE.startExport = function () {
    YWCE.debug.log("YWCE: Starting export process");

    if (!YWCE.validateProductDateRange()) {
      return false;
    }

    var exportName = jQuery("#export-name").val();
    if (!exportName) {
      YWCE.showWarningModal(YWCE.__("Please enter an export name"));
      return;
    }

    if (YWCE.state.selectedFormats.length === 0) {
      YWCE.showWarningModal(
        YWCE.__("Please select at least one export format")
      );
      return;
    }

    if (YWCE.state.selectedFields.length === 0) {
      YWCE.showWarningModal("Please select at least one field to export");
      return;
    }

    YWCE.debug.log("YWCE: Export name:", exportName);
    YWCE.debug.log("YWCE: Selected formats:", YWCE.state.selectedFormats);
    YWCE.debug.log(
      "YWCE: Selected data source:",
      YWCE.state.selectedDataSource
    );
    YWCE.debug.log("YWCE: Selected fields:", YWCE.state.selectedFields);
    YWCE.debug.log("YWCE: Selected meta fields:", YWCE.state.selectedMeta);
    YWCE.debug.log("YWCE: Selected taxonomies:", YWCE.state.selectedTaxonomies);

    jQuery("#ywce-download-links").html("").hide();
    jQuery("#ywce-export-completed-msg").hide();

    const exportBtn = jQuery("#ywce-export-btn");
    exportBtn.text(YWCE.__("Start Export"));
    exportBtn.removeClass("btn-outline-primary").addClass("btn-primary");
    exportBtn.prop("disabled", true);

    jQuery("#ywce-export-progress-container").show();

    jQuery("#ywce-export-progress").css("width", "0%").attr("aria-valuenow", 0);
    jQuery("#ywce-export-status").text(YWCE.__("Initializing export..."));

    YWCE.currentFormat = null;

    var formData = new FormData();
    formData.append("action", "ywce_start_export");
    formData.append("nonce", ywce_ajax.nonce);
    formData.append("data_source", YWCE.state.selectedDataSource);
    formData.append("export_name", exportName);
    formData.append("formats", JSON.stringify(YWCE.state.selectedFormats));

    formData.append("fields", JSON.stringify(YWCE.state.selectedFields));
    formData.append("meta_fields", JSON.stringify(YWCE.state.selectedMeta));
    formData.append(
      "taxonomies",
      JSON.stringify(YWCE.state.selectedTaxonomies)
    );

    if (YWCE.state.selectedColumns && YWCE.state.selectedColumns.length > 0) {
      formData.append(
        "column_order",
        JSON.stringify(YWCE.state.selectedColumns)
      );
    }

    if (YWCE.state.selectedHeaders) {
      formData.append(
        "custom_headers",
        JSON.stringify(YWCE.state.selectedHeaders)
      );
    }

    if (YWCE.state.selectedDataSource === "product") {
      const productTypes = jQuery("#product-type").val() || ["all"];
      formData.append("product_types", JSON.stringify(productTypes));

      const productStatus = jQuery("#product-status").val() || ["all"];
      formData.append("product_status", JSON.stringify(productStatus));

      const exportMode = jQuery("#product-export-mode").val() || "all";
      formData.append("product_export_mode", exportMode);

      if (exportMode !== "all") {
        const dateRange = jQuery("#product-date-range").val() || "last30";
        formData.append("product_date_range", dateRange);

        if (dateRange === "custom") {
          formData.append(
            "product_date_from",
            jQuery("#product-date-from").val()
          );
          formData.append("product_date_to", jQuery("#product-date-to").val());
        }
      }
    } else if (YWCE.state.selectedDataSource === "user") {
      const selectedUserRoles = jQuery("#user-role").val() || ["all"];
      formData.append("user_roles", JSON.stringify(selectedUserRoles));

      const userDateRange = jQuery("#user-date-range").val();
      formData.append("user_date_range", userDateRange);

      if (userDateRange === "custom") {
        formData.append("user_date_from", jQuery("#user-date-from").val());
        formData.append("user_date_to", jQuery("#user-date-to").val());
      }
    } else if (YWCE.state.selectedDataSource === "order") {
      const selectedOrderStatuses = jQuery("#order-status").val() || ["all"];
      formData.append("order_statuses", JSON.stringify(selectedOrderStatuses));

      const orderDateRange = jQuery("#order-date-range").val();
      formData.append("order_date_range", orderDateRange);

      if (orderDateRange === "custom") {
        formData.append("order_date_from", jQuery("#order-date-from").val());
        formData.append("order_date_to", jQuery("#order-date-to").val());
      }
    }

    YWCE.debug.log("YWCE: Sending AJAX request to start export");

    jQuery.ajax({
      url: ywce_ajax.ajax_url,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        YWCE.debug.log("YWCE: Export start response:", response);
        if (response.success) {
          YWCE.state.exportId = response.data.export_id;
          YWCE.state.exportQueue = response.data.formats || [];
          YWCE.state.currentExportIndex = 0;
          YWCE.state.isExporting = true;

          YWCE.debug.log("YWCE: Export ID:", YWCE.state.exportId);
          YWCE.debug.log("YWCE: Export queue:", YWCE.state.exportQueue);

          // Process the export
          YWCE.processExport(YWCE.state.exportId);
        } else {
          YWCE.debug.error("YWCE: Export start error:", response.data);

          const errorHtml = `
                        <div class="alert alert-warning" role="alert">
                            <h5 class="alert-heading mb-2">${YWCE.__(
                              "Export Error"
                            )}</h5>
                            <p class="mb-0">${
                              response.data.message || YWCE.__("Unknown error")
                            }</p>
                        </div>
                    `;

          jQuery("#ywce-export-status").html(errorHtml).addClass("has-error");

          jQuery("#ywce-export-btn").prop("disabled", false);
        }
      },
      error: function (xhr, status, error) {
        YWCE.debug.error("YWCE: AJAX error:", status, error);
        YWCE.debug.error("YWCE: Response text:", xhr.responseText);

        let errorMessage = error;
        try {
          const response = JSON.parse(xhr.responseText);
          if (response.data && response.data.message) {
            errorMessage = response.data.message;
          }
        } catch (e) {
          YWCE.debug.error("YWCE: Error parsing response:", e);
        }

        const errorHtml = `
                    <div class="alert alert-warning" role="alert">
                        <h5 class="alert-heading mb-2">${YWCE.__(
                          "Export Error"
                        )}</h5>
                        <p class="mb-0">${errorMessage}</p>
                    </div>
                `;

        jQuery("#ywce-export-status").html(errorHtml).addClass("has-error");

        jQuery("#ywce-export-btn").prop("disabled", false);
      },
    });
  };

  YWCE.processExport = function (exportId) {
    YWCE.debug.log("YWCE: Processing export with ID:", exportId);

    const progressBar = jQuery("#ywce-export-progress-container .progress-bar");
    const statusText = jQuery("#ywce-export-status");

    if (progressBar.length === 0) {
      YWCE.debug.error("YWCE: Progress bar not found");
      return;
    }

    if (statusText.length === 0) {
      YWCE.debug.error("YWCE: Status text not found");
      return;
    }

    jQuery("#ywce-export-progress-container").show();

    const formData = new FormData();
    formData.append("action", "ywce_process_export");
    formData.append("export_id", exportId);
    formData.append("nonce", ywce_ajax.nonce);

    fetch(ywce_ajax.ajax_url, {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    })
      .then((response) => response.json())
      .then((data) => {
        if (!data || !data.success || !data.data) {
          YWCE.debug.error("YWCE: Invalid response data:", data);
          statusText.text(YWCE.__("Error: Invalid response data"));
          statusText.addClass("text-danger");
          return;
        }

        const responseData = data.data;
        const progress = responseData.progress || 0;
        const format = responseData.format || YWCE.currentFormat;

        progressBar.css("width", progress + "%");
        progressBar.attr("aria-valuenow", progress);

        let formatText = format ? format.toUpperCase() : "Unknown";
        if (format === "excel") formatText = "XLSX";

        if (format !== YWCE.currentFormat) {
          YWCE.debug.log(
            "YWCE: Format changed from",
            YWCE.currentFormat,
            "to",
            format
          );
          YWCE.currentFormat = format;
          statusText.text(YWCE.__("Initializing export..."));

          setTimeout(() => YWCE.processExport(exportId), 500);
          return;
        }

        if (responseData.completed) {
          YWCE.debug.log("YWCE: Export completed");
          YWCE.finishCurrentExport(responseData);
        } else if (responseData.format_completed) {
          YWCE.debug.log("YWCE: Format completed:", format);
          statusText.text(YWCE.__("Format Completed:") + " " + formatText);

          setTimeout(() => YWCE.processExport(exportId), 500);
        } else {
          statusText.text(
            `${YWCE.__("Processing")} ${formatText} ${YWCE.__(
              "format:"
            )} ${progress}% ${YWCE.__("complete")}`
          );

          setTimeout(() => YWCE.processExport(exportId), 500);
        }
      })
      .catch((error) => {
        YWCE.debug.error("YWCE: Process export error:", error);
        statusText.text(YWCE.__("Error processing export: ") + error.message);
        statusText.addClass("text-danger");
      });
  };

  YWCE.finishCurrentExport = function (data) {
    YWCE.debug.log("YWCE: Finishing export with data:", data);

    if (data.completed) {
      YWCE.debug.log("YWCE: All formats completed");

      jQuery("#ywce-export-progress-container").hide();

      if (data.file_urls && data.formats) {
        YWCE.debug.log("YWCE: File URLs:", data.file_urls);
        YWCE.debug.log("YWCE: Formats:", data.formats);

        const downloadLinks = jQuery("#ywce-download-links");
        if (downloadLinks.length > 0) {
          downloadLinks.html("");
          downloadLinks.show();
        } else {
          YWCE.debug.error("YWCE: Download links container not found");
        }

        data.formats.forEach(function (format) {
          YWCE.debug.log("YWCE: Processing format:", format);

          if (data.file_urls[format]) {
            YWCE.debug.log(
              "YWCE: File URL for format " + format + ":",
              data.file_urls[format]
            );

            const exportItem = {
              format: format,
              fileUrl: data.file_urls[format],
              fileName:
                data.file_names && data.file_names[format]
                  ? data.file_names[format]
                  : data.export_name + "." + YWCE.getFileExtension(format),
            };

            YWCE.debug.log("YWCE: Creating download link for:", exportItem);
            YWCE.addDownloadLink(exportItem);
          } else {
            YWCE.debug.error(`YWCE: No file URL for format ${format}`);
          }
        });
      } else {
        YWCE.debug.error("YWCE: No file URLs or formats in response");
      }

      const exportBtn = jQuery("#ywce-export-btn");
      exportBtn.text(YWCE.__("Export Again"));
      exportBtn.removeClass("btn-primary").addClass("btn-outline-primary");
      exportBtn.prop("disabled", false);

      YWCE.state.fieldsModifiedAfterExport = false;

      YWCE.currentFormat = null;

      jQuery("#ywce-export-completed-msg").show();
    } else if (data.next_format) {
      YWCE.debug.log("YWCE: Processing next format:", data.next_format);

      setTimeout(() => YWCE.processExport(data.export_id), 500);
    }
  };

  YWCE.getFileExtension = function (format) {
    switch (format) {
      case "excel":
        return "xlsx";
      case "xml":
        return "xml";
      case "json":
        return "json";
      case "csv":
      default:
        return "csv";
    }
  };

  YWCE.addDownloadLink = function (exportItem) {
    YWCE.debug.log("YWCE: Adding download link for:", exportItem);

    const downloadLinks = jQuery("#ywce-download-links");
    if (downloadLinks.length === 0) {
      YWCE.debug.error("YWCE: Download links container not found");
      return;
    }

    if (!exportItem || !exportItem.format || !exportItem.fileUrl) {
      YWCE.debug.error("YWCE: Invalid export item:", exportItem);
      return;
    }

    downloadLinks.show();

    let formatLabel, formatIcon, formatClass;
    switch (exportItem.format) {
      case "excel":
        formatLabel = "XLSX";
        formatIcon = "dashicons-media-spreadsheet";
        formatClass = "excel-link";
        break;
      case "csv":
        formatLabel = "CSV";
        formatIcon = "dashicons-media-text";
        formatClass = "csv-link";
        break;
      case "xml":
        formatLabel = "XML";
        formatIcon = "dashicons-media-code";
        formatClass = "xml-link";
        break;
      case "json":
        formatLabel = "JSON";
        formatIcon = "dashicons-media-code";
        formatClass = "json-link";
        break;
      default:
        formatLabel = exportItem.format.toUpperCase();
        formatIcon = "dashicons-media-default";
        formatClass = "default-link";
    }

    const link = jQuery("<a></a>")
      .attr("href", exportItem.fileUrl)
      .attr("target", "_blank")
      .attr("role", "button")
      .attr("aria-label", `Download ${formatLabel} file`)
      .addClass(`download-link ${formatClass}`);

    const icon = jQuery("<span></span>")
      .addClass("dashicons " + formatIcon)
      .css("margin-right", "8px");

    const text = jQuery("<span></span>").text(
      YWCE.__("Download") + " " + formatLabel
    );

    link.append(icon);
    link.append(text);

    downloadLinks.append(link);

    YWCE.debug.log("YWCE: Download link added for format:", exportItem.format);
  };

  YWCE.completeAllExports = function () {
    YWCE.debug.log("YWCE: Completing all exports");

    jQuery("#ywce-export-progress-container").hide();
    jQuery("#ywce-export-btn").prop("disabled", false);
    jQuery("#ywce-export-completed-msg").show();

    jQuery(".step-4").addClass("export-complete");
  };

  YWCE.handleExportError = function (message) {
    YWCE.debug.error("YWCE: Export error:", message);

    const statusText = document.getElementById("ywce-export-status");
    const exportBtn = document.getElementById("ywce-export-btn");

    if (statusText) {
      statusText.textContent = message;
      statusText.classList.add("text-danger");
    }

    if (exportBtn) {
      exportBtn.disabled = false;
    }

    YWCE.state.isExporting = false;
  };

  YWCE.addFieldSelectionListeners = function () {
    const dataFieldsSelect = document.getElementById("ywce-data-fields");
    const metaFieldsSelect = document.getElementById("ywce-meta-fields");
    const taxonomyFieldsSelect = document.getElementById(
      "ywce-taxonomy-fields"
    );

    if (dataFieldsSelect) {
      dataFieldsSelect.addEventListener("change", function () {
        YWCE.state.selectedFields = Array.from(this.selectedOptions).map(
          (option) => option.value
        );
        YWCE.debug.log(
          "YWCE: Selected fields updated:",
          YWCE.state.selectedFields
        );
      });
    }

    if (metaFieldsSelect) {
      metaFieldsSelect.addEventListener("change", function () {
        YWCE.state.selectedMeta = Array.from(this.selectedOptions).map(
          (option) => option.value
        );
        YWCE.debug.log("YWCE: Selected meta updated:", YWCE.state.selectedMeta);
      });
    }

    if (taxonomyFieldsSelect) {
      taxonomyFieldsSelect.addEventListener("change", function () {
        YWCE.state.selectedTaxonomies = Array.from(this.selectedOptions).map(
          (option) => option.value
        );
        YWCE.debug.log(
          "YWCE: Selected taxonomies updated:",
          YWCE.state.selectedTaxonomies
        );
      });
    }
  };

  YWCE.updateNextButtonState = function () {
    const nextButton = document.getElementById("ywce-next-step");
    if (!nextButton) return;

    if (YWCE.state.currentStep === 1) {
      nextButton.disabled = !YWCE.state.selectedDataSource;
    } else if (YWCE.state.currentStep === 2) {
      const hasSelectedFields =
        YWCE.state.selectedFields.length > 0 ||
        YWCE.state.selectedMeta.length > 0 ||
        YWCE.state.selectedTaxonomies.length > 0;

      let requiredFieldsSelected = true;
      const dataFieldsSelect = document.getElementById("ywce-data-fields");

      if (dataFieldsSelect) {
        const requiredFields = Array.from(
          document.querySelectorAll(".field-item.required")
        ).map((item) => item.dataset.value);

        if (requiredFields.length > 0) {
          requiredFieldsSelected = requiredFields.every((field) => {
            const option = dataFieldsSelect.querySelector(
              `option[value="${field}"]`
            );
            return option && option.selected;
          });
        }
      }

      nextButton.disabled = !hasSelectedFields || !requiredFieldsSelected;
    } else if (YWCE.state.currentStep === 3) {
      nextButton.disabled = false;
    }
  };

  YWCE.debugState = function () {
    YWCE.debug.log("YWCE Debug State:");
    YWCE.debug.log("- Current Step:", YWCE.state.currentStep);
    YWCE.debug.log("- Selected Data Source:", YWCE.state.selectedDataSource);
    YWCE.debug.log("- Selected Fields:", YWCE.state.selectedFields);
    YWCE.debug.log("- Selected Meta:", YWCE.state.selectedMeta);
    YWCE.debug.log("- Selected Taxonomies:", YWCE.state.selectedTaxonomies);
    YWCE.debug.log("- Selected Columns:", YWCE.state.selectedColumns);
    YWCE.debug.log("- Last Preview Data:", YWCE.state.lastPreviewData);

    YWCE.debug.log("DOM Elements:");
    YWCE.debug.log(
      "- Data Source Buttons:",
      document.querySelectorAll("#ywce-data-source button").length
    );
    YWCE.debug.log(
      "- Data Fields Select:",
      !!document.getElementById("ywce-data-fields")
    );
    YWCE.debug.log(
      "- Meta Fields Select:",
      !!document.getElementById("ywce-meta-fields")
    );
    YWCE.debug.log(
      "- Taxonomy Fields Select:",
      !!document.getElementById("ywce-taxonomy-fields")
    );
    YWCE.debug.log(
      "- Next Button:",
      !!document.getElementById("ywce-next-step")
    );
    YWCE.debug.log(
      "- Prev Button:",
      !!document.getElementById("ywce-prev-step")
    );
    YWCE.debug.log(
      "- Preview Table:",
      !!document.querySelector("#ywce-wizard .step-3 table")
    );
  };

  YWCE.fetchProductTypes = function () {
    if (YWCE.state.availableProductTypes.length > 0) {
      YWCE.populateProductTypes();
      return;
    }

    jQuery.ajax({
      url: ywce_ajax.ajax_url,
      type: "POST",
      data: {
        action: "ywce_fetch_product_types",
        security: ywce_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          YWCE.state.availableProductTypes = response.data;
          YWCE.populateProductTypes();
        } else {
          YWCE.debug.error("Error fetching product types:", response.data);
        }
      },
      error: function (xhr, status, error) {
        YWCE.debug.error("AJAX error fetching product types:", error);
      },
    });
  };

  YWCE.populateProductTypes = function () {
    var $container = jQuery("#product-type").prev(".field-list-container");

    $container.find('.field-item:not([data-value="all"])').remove();

    if (
      YWCE.state.availableProductTypes &&
      YWCE.state.availableProductTypes.length > 0
    ) {
      jQuery.each(YWCE.state.availableProductTypes, function (index, type) {
        $container.append(
          '<div class="field-item" data-value="' +
            type.value +
            '">' +
            type.label +
            "</div>"
        );
      });
    } else {
      var defaultTypes = [
        { value: "simple", label: "Simple Product" },
        { value: "variable", label: "Variable Product" },
        { value: "grouped", label: "Grouped Product" },
        { value: "external", label: "External Product" },
      ];

      jQuery.each(defaultTypes, function (index, type) {
        $container.append(
          '<div class="field-item" data-value="' +
            type.value +
            '">' +
            type.label +
            "</div>"
        );
      });
    }

    $container.find('.field-item[data-value="all"]').addClass("selected");

    YWCE.setupFieldItemSelection();
  };

  YWCE.fetchUserRoles = function () {
    if (YWCE.state.availableUserRoles.length > 0) {
      YWCE.populateUserRoles();
      return;
    }

    jQuery.ajax({
      url: ywce_ajax.ajax_url,
      type: "POST",
      data: {
        action: "ywce_fetch_user_roles",
        security: ywce_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          YWCE.state.availableUserRoles = response.data;
          YWCE.populateUserRoles();
        } else {
          YWCE.debug.error("Error fetching user roles:", response.data);
        }
      },
      error: function (xhr, status, error) {
        YWCE.debug.error("AJAX error fetching user roles:", error);
      },
    });
  };

  YWCE.populateUserRoles = function () {
    var $container = jQuery("#user-role").prev(".field-list-container");

    $container.find('.field-item:not([data-value="all"])').remove();

    if (
      YWCE.state.availableUserRoles &&
      YWCE.state.availableUserRoles.length > 0
    ) {
      jQuery.each(YWCE.state.availableUserRoles, function (index, role) {
        $container.append(
          '<div class="field-item" data-value="' +
            role.value +
            '">' +
            role.label +
            "</div>"
        );
      });
    } else {
      var defaultRoles = [
        { value: "administrator", label: "Administrator" },
        { value: "editor", label: "Editor" },
        { value: "author", label: "Author" },
        { value: "contributor", label: "Contributor" },
        { value: "subscriber", label: "Subscriber" },
        { value: "customer", label: "Customer" },
      ];

      jQuery.each(defaultRoles, function (index, role) {
        $container.append(
          '<div class="field-item" data-value="' +
            role.value +
            '">' +
            role.label +
            "</div>"
        );
      });
    }

    $container.find('.field-item[data-value="all"]').addClass("selected");

    YWCE.setupFieldItemSelection();
  };

  YWCE.fetchOrderStatuses = function () {
    if (YWCE.state.availableOrderStatuses.length > 0) {
      YWCE.populateOrderStatuses();
      return;
    }

    jQuery.ajax({
      url: ywce_ajax.ajax_url,
      type: "POST",
      data: {
        action: "ywce_fetch_order_statuses",
        security: ywce_ajax.nonce,
      },
      success: function (response) {
        if (response.success) {
          YWCE.state.availableOrderStatuses = response.data;
          YWCE.populateOrderStatuses();
        } else {
          YWCE.debug.error("Error fetching order statuses:", response.data);
        }
      },
      error: function (xhr, status, error) {
        YWCE.debug.error("AJAX error fetching order statuses:", error);
      },
    });
  };

  YWCE.populateOrderStatuses = function () {
    var $container = jQuery("#order-status").prev(".field-list-container");

    $container.find('.field-item:not([data-value="all"])').remove();

    if (
      YWCE.state.availableOrderStatuses &&
      YWCE.state.availableOrderStatuses.length > 0
    ) {
      jQuery.each(YWCE.state.availableOrderStatuses, function (index, status) {
        $container.append(
          '<div class="field-item" data-value="' +
            status.value +
            '">' +
            status.label +
            "</div>"
        );
      });
    } else {
      var defaultStatuses = [
        { value: "wc-pending", label: "Pending payment" },
        { value: "wc-processing", label: "Processing" },
        { value: "wc-on-hold", label: "On hold" },
        { value: "wc-completed", label: "Completed" },
        { value: "wc-cancelled", label: "Cancelled" },
        { value: "wc-refunded", label: "Refunded" },
        { value: "wc-failed", label: "Failed" },
      ];

      jQuery.each(defaultStatuses, function (index, status) {
        $container.append(
          '<div class="field-item" data-value="' +
            status.value +
            '">' +
            status.label +
            "</div>"
        );
      });
    }

    $container.find('.field-item[data-value="all"]').addClass("selected");

    YWCE.setupFieldItemSelection();
  };

  YWCE.formatDate = function (date) {
    var year = date.getFullYear();
    var month = ("0" + (date.getMonth() + 1)).slice(-2);
    var day = ("0" + date.getDate()).slice(-2);
    return year + "-" + month + "-" + day;
  };

  YWCE.setupExportButtonAnimation = function () {
    jQuery("#ywce-export-btn").hover(
      function () {
        jQuery(this).addClass("pulse-animation");
      },
      function () {
        jQuery(this).removeClass("pulse-animation");
      }
    );
  };

  document.addEventListener("DOMContentLoaded", function () {
    YWCE.debug.log("YWCE: DOM content loaded, initializing...");

    const wizardContainer = document.getElementById("ywce-wizard");
    if (wizardContainer) {
      YWCE.debug.log("YWCE: Wizard container found, initializing...");
      YWCE.init();
    } else {
      YWCE.debug.log(
        "YWCE: Wizard container not found, skipping initialization."
      );
    }
  });

  YWCE.nextStep = function () {
    YWCE.debug.log(
      "YWCE: Next step called, current step:",
      YWCE.state.currentStep
    );
    YWCE.debug.log(
      "YWCE: Selected data source:",
      YWCE.state.selectedDataSource
    );

    if (YWCE.state.currentStep === 1 && !YWCE.state.selectedDataSource) {
      alert("Please select a data source first");
      return;
    }

    if (
      YWCE.state.currentStep === 2 &&
      YWCE.state.selectedFields.length === 0
    ) {
      alert("Please select at least one field to export");
      return;
    }

    YWCE.showStep(YWCE.state.currentStep + 1);
  };

  YWCE.updateDataTypeLabel = function () {
    const dataTypeLabel = document.getElementById("ywce-data-type-label");
    if (!dataTypeLabel) return;

    let labelText = "data";

    switch (YWCE.state.selectedDataSource) {
      case "product":
        labelText = YWCE.__("product data");
        break;
      case "user":
        labelText = YWCE.__("user data");
        break;
      case "order":
        labelText = YWCE.__("order data");
        break;
    }

    dataTypeLabel.textContent = labelText;
    YWCE.debug.log("YWCE: Updated data type label to:", labelText);
  };

  YWCE.setupFieldItemSelection = function () {
    YWCE.debug.log("YWCE: Setting up field item selection for step 2");

    var fieldContainers = jQuery(".step-2 .field-list-container");

    fieldContainers.each(function () {
      var $container = jQuery(this);
      var containerId = $container.attr("id");
      var selectId = containerId.replace("-container", "");

      YWCE.debug.log(
        "YWCE: Setting up field container:",
        containerId,
        "for select:",
        selectId
      );

      $container
        .find(".field-item")
        .off("click")
        .on("click", function () {
          var $this = jQuery(this);
          var $select = jQuery("#" + selectId);
          var value = $this.data("value");

          YWCE.debug.log(
            "YWCE: Field item clicked:",
            value,
            "in container:",
            containerId,
            "for select:",
            selectId
          );

          if ($this.hasClass("required")) {
            $this.addClass("selected");
            return;
          }

          $this.toggleClass("selected");

          var option = $select.find('option[value="' + value + '"]');
          if ($this.hasClass("selected")) {
            option.prop("selected", true);
          } else {
            option.prop("selected", false);
          }

          if (selectId === "ywce-data-fields") {
            YWCE.state.selectedFields = $select.val() || [];
          } else if (selectId === "ywce-meta-fields") {
            YWCE.state.selectedMeta = $select.val() || [];
          } else if (selectId === "ywce-taxonomy-fields") {
            YWCE.state.selectedTaxonomies = $select.val() || [];
          }

          YWCE.state.fieldsModifiedAfterExport = true;

          YWCE.updateExportSummary();
        });
    });

    jQuery(".step-2 .select-all-btn")
      .off("click")
      .on("click", function () {
        var targetId = jQuery(this).data("target");
        var $container = jQuery("#" + targetId);
        var selectId = targetId.replace("-container", "");
        var $select = jQuery("#" + selectId);

        YWCE.debug.log(
          "YWCE: Select All clicked for container:",
          targetId,
          "and select:",
          selectId
        );

        $container.find(".field-item").addClass("selected");

        $select.find("option").prop("selected", true);

        if (selectId === "ywce-data-fields") {
          YWCE.state.selectedFields = $select.val() || [];
        } else if (selectId === "ywce-meta-fields") {
          YWCE.state.selectedMeta = $select.val() || [];
        } else if (selectId === "ywce-taxonomy-fields") {
          YWCE.state.selectedTaxonomies = $select.val() || [];
        }

        YWCE.state.fieldsModifiedAfterExport = true;

        YWCE.updateExportSummary();
      });

    jQuery(".step-2 .deselect-all-btn")
      .off("click")
      .on("click", function () {
        var targetId = jQuery(this).data("target");
        var $container = jQuery("#" + targetId);
        var selectId = targetId.replace("-container", "");
        var $select = jQuery("#" + selectId);

        YWCE.debug.log(
          "YWCE: Deselect All clicked for container:",
          targetId,
          "and select:",
          selectId
        );

        $container.find(".field-item").not(".required").removeClass("selected");

        $select
          .find("option")
          .not(function () {
            return (
              $container.find(
                '.field-item.required[data-value="' + this.value + '"]'
              ).length > 0
            );
          })
          .prop("selected", false);

        if (selectId === "ywce-data-fields") {
          YWCE.state.selectedFields = $select.val() || [];
        } else if (selectId === "ywce-meta-fields") {
          YWCE.state.selectedMeta = $select.val() || [];
        } else if (selectId === "ywce-taxonomy-fields") {
          YWCE.state.selectedTaxonomies = $select.val() || [];
        }

        YWCE.state.fieldsModifiedAfterExport = true;

        YWCE.updateExportSummary();
      });
  };

  jQuery("#product-export-mode").on("change", function () {
    var mode = jQuery(this).val();
    var dateRangeContainer = jQuery("#product-date-range-container");
    var customDateRange = jQuery("#product-custom-date-range");

    if (mode === "modified" || mode === "new") {
      dateRangeContainer.slideDown();
    } else {
      dateRangeContainer.slideUp();
      customDateRange.slideUp();
      jQuery("#product-date-range").val("last30");
    }
  });

  jQuery("#product-date-range").on("change", function () {
    var range = jQuery(this).val();
    var customDateRange = jQuery("#product-custom-date-range");

    if (range === "custom") {
      customDateRange.slideDown();
    } else {
      customDateRange.slideUp();
    }
  });

  jQuery(document).ready(function () {
    var today = new Date();
    var thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);

    jQuery("#product-date-from").val(thirtyDaysAgo.toISOString().split("T")[0]);
    jQuery("#product-date-to").val(today.toISOString().split("T")[0]);
  });

  function updateExportSummary() {
    const exportName = jQuery("#export-name").val();
    const format = jQuery(".format-btn.active").data("format") || "";
    const dataSource = jQuery("#ywce-selected-source").val();
    const selectedFields = getSelectedFields();
    const productTypes = getSelectedProductTypes();
    const productStatus = getSelectedProductStatus();

    let dataSourceText = "";
    switch (dataSource) {
      case "product":
        dataSourceText = YWCE.__("productsDataSource");
        break;
      case "order":
        dataSourceText = YWCE.__("ordersDataSource");
        break;
      case "user":
        dataSourceText = YWCE.__("usersDataSource");
        break;
    }

    jQuery("#summary-export-name").text(exportName);
    jQuery("#summary-format").text(format.toUpperCase());
    jQuery("#summary-data-source").text(dataSourceText);

    if (dataSource === "product") {
      jQuery(".product-summary-item").show();
      jQuery("#summary-product-types").text(
        productTypes.length ? productTypes.join(", ") : YWCE.__("allTypes")
      );
      jQuery("#summary-product-status").text(
        productStatus.length ? productStatus.join(", ") : YWCE.__("allStatuses")
      );
    } else {
      jQuery(".product-summary-item").hide();
    }

    const totalFields = selectedFields.length;
    jQuery("#summary-fields-count").text(
      totalFields + " " + YWCE.__("fieldsSelected")
    );

    const fieldsList = jQuery("#summary-selected-fields");
    fieldsList.empty();

    if (selectedFields.length > 0) {
      selectedFields.sort();

      const fieldsHtml = selectedFields
        .map((field) => {
          return (
            '<span class="badge bg-light text-dark me-2 mb-2">' +
            field +
            "</span>"
          );
        })
        .join("");

      fieldsList.html(
        '<div class="d-flex flex-wrap gap-2">' + fieldsHtml + "</div>"
      );
    } else {
      fieldsList.html(
        '<div class="text-muted">' + YWCE.__("allFields") + "</div>"
      );
    }
  }

  function getSelectedFields() {
    const dataFields = jQuery("#ywce-data-fields").val() || [];
    const metaFields = jQuery("#ywce-meta-fields").val() || [];
    const taxonomyFields = jQuery("#ywce-taxonomy-fields").val() || [];
    return [...dataFields, ...metaFields, ...taxonomyFields];
  }

  function getSelectedProductTypes() {
    return jQuery("#product-type").val() || [];
  }

  function getSelectedProductStatus() {
    return jQuery("#product-status").val() || [];
  }
})(window, document);
