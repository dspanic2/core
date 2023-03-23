function showStep(n) {


    // This function will display the specified tab of the form ...
    var x = jQuery('.sp-step[data-step-number=' + n + ']');
    x.css("display", "block");
    // ... and fix the Previous/Next buttons:
    if (n == 0) {
        document.getElementById("prevBtn").style.display = "none";
    } else {
        document.getElementById("prevBtn").style.display = "inline";
    }
    if (n == (document.getElementsByClassName("sp-step").length - 1)) {
        document.getElementById("nextBtn").style.display = "none";
        jQuery('button[data-action="return"]').show();
    } else {
        document.getElementById("nextBtn").style.display = "inline";
        jQuery('button[data-action="return"]').hide();
    }
    // ... and run a function that displays the correct step indicator:
    fixStepIndicator(n)
}

function nextPrev(n) {

    var currentStep = 0;

    jQuery(".sp-step").each(function () {
        if (jQuery(this).css("display") == "block") {
            currentStep = jQuery(this).data("step-number");
        }
    })

    // This function will figure out which tab to display
    var x = document.getElementsByClassName("sp-step");
    // Exit the function if any field in the current tab is invalid:
    if (n == 1 && !validateForm(currentStep)) return false;
    // Hide the current tab:
    x[currentStep].style.display = "none";
    // Increase or decrease the current tab by 1:
    currentStep = currentStep + n;
    // if you have reached the end of the form... :

    // Otherwise, display the correct tab:
    showStep(currentStep);
}

function validateForm(currentTabNumber) {
    var valid = true;
    // This function deals with validation of the form fields for current tab
    var currentStep = jQuery('.sp-step[data-step-number=' + currentTabNumber + ']');
    var form = jQuery('form[data-validate="true"]');

    var formcntrols = currentStep.find(".form-control");

    formcntrols.each(function () {
        form.formValidation('revalidateField', jQuery(this).attr("name"));
    });

    if (currentStep.find(".has-error").length > 0)
        valid = false;

    return valid; // return the valid status
}

function fixStepIndicator(n) {
    // This function removes the "active" class of all steps...
    var i, x = document.getElementsByClassName("sp-step-dot");
    for (i = 0; i < x.length; i++) {
        x[i].className = x[i].className.replace(" active", "");
    }
    //... and adds the "active" class to the current step:
    x[n].className += " active";
}

jQuery(document).ready(function () {
    if (jQuery(".sp-step").length > 0) {
        showStep(0); // Display the current step
    }

    jQuery(".sp-left-panel").click(function() {
        jQuery(this).children().hide();
        jQuery(".sp-main-panel").parent().removeClass().addClass("col-lg-12 col-md-12 col-sm-12 col-xs-12");
    });
});


