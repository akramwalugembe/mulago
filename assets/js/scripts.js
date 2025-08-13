
  // Show restock modal with drug info
  function showRestockModal(drugId, drugName) {
    $("#restockDrugId").val(drugId);
    $("#drugName").val(drugName);
    $("#restockModal").modal("show");
  }

  // Initialize datepicker for expiry date
  $("#expiryDate").attr("min", new Date().toISOString().split("T")[0]);

  // Generate a simple batch number if empty
  $("#batchNumber").on("focus", function () {
    if (!this.value) {
      this.value =
        "BATCH-" +
        Math.floor(Math.random() * 10000)
          .toString()
          .padStart(4, "0");
    }
  });

  function showAdjustModal(inventoryId, drugName) {
    $("#adjustInventoryId").val(inventoryId);
    $("#adjustDrugName").val(drugName);
    $("#adjustNotes").val("");
    $("#adjustment").val(1);
    $("#adjustmentType").val("1");
    $("#adjustModal").modal("show");
  }

  // Show transfer stock modal
  function showTransferModal(inventoryId, drugName, currentStock) {
    $("#transferInventoryId").val(inventoryId);
    $("#transferDrugName").val(drugName);
    $("#transferQuantity").attr("max", currentStock).val(1);
    $("#currentStock").text(currentStock);
    $("#toDepartment").val("");
    $("#transferNotes").val("");
    $("#transferModal").modal("show");
  }

  $("#inventoryTable").DataTable({
    pageLength: 25,
    order: [
      [2, "asc"],
      [0, "asc"],
    ],
    columnDefs: [{ orderable: false, targets: [6] }],
  });

  // Update adjustment value based on type
  $("#adjustmentType").change(function () {
    var currentVal = $("#adjustment").val();
    if (currentVal < 0) {
      $("#adjustment").val(Math.abs(currentVal));
    }
  });

  // Show edit modal with user data
  function showEditModal(
    userId,
    username,
    fullName,
    email,
    departmentId,
    role,
    isActive
  ) {
    $("#editUserId").val(userId);
    $("#editUsername").val(username);
    $("#editFullName").val(fullName);
    $("#editEmail").val(email);
    $("#editDepartment").val(departmentId || "");
    $("#editRole").val(role);
    $("#editIsActive").prop("checked", isActive);
    $("#editUserModal").modal("show");
  }

  // Show password change modal
  function showPasswordModal(userId, username) {
    $("#passwordUserId").val(userId);
    $("#passwordUsername").val(username);
    $("#newPassword").val("");
    $("#passwordModal").modal("show");
  }

  // Show delete confirmation modal
  function confirmDelete(userId, username) {
    $("#deleteUserId").val(userId);
    $("#deleteUsername").text(username);
    $("#deleteModal").modal("show");
  }

  $("#usersTable").DataTable({
    pageLength: 25,
    order: [
      [4, "asc"],
      [1, "asc"],
    ],
    columnDefs: [{ orderable: false, targets: [7] }],
  });

  // Show edit modal with drug data
function showEditModal(drugId, drugName, genericName, categoryId, unitOfMeasure, reorderLevel, description, isActive) {
    $('#editDrugId').val(drugId);
    $('#editDrugName').val(drugName);
    $('#editGenericName').val(genericName);
    $('#editCategory').val(categoryId || '');
    $('#editUnit').val(unitOfMeasure);
    $('#editReorderLevel').val(reorderLevel);
    $('#editDescription').val(description || '');
    $('#editIsActive').prop('checked', isActive);
    $('#editDrugModal').modal('show');
}

// Show delete confirmation modal
function confirmDelete(drugId, drugName) {
    $('#deleteDrugId').val(drugId);
    $('#deleteDrugName').text(drugName);
    $('#deleteModal').modal('show');
}

// Show delete confirmation modal
function confirmDelete(transactionId, drugName) {
    $('#deleteTransactionId').val(transactionId);
    $('#deleteModal').modal('show');
}



// Show edit department modal
function showEditDepartmentModal(id, name, location, contactPerson, contactPhone, isActive) {
    $('#editDeptId').val(id);
    $('#editDeptName').val(name);
    $('#editDeptLocation').val(location);
    $('#editDeptContactPerson').val(contactPerson);
    $('#editDeptContactPhone').val(contactPhone);
    $('#editDeptIsActive').prop('checked', isActive);
    $('#editDepartmentModal').modal('show');
}

// Show edit category modal
function showEditCategoryModal(id, name, description) {
    $('#editCatId').val(id);
    $('#editCatName').val(name);
    $('#editCatDescription').val(description);
    $('#editCategoryModal').modal('show');
}

// Show edit supplier modal
function showEditSupplierModal(id, name, contactPerson, contactPhone, email, address, isActive) {
    $('#editSupplierId').val(id);
    $('#editSupplierName').val(name);
    $('#editSupplierContactPerson').val(contactPerson);
    $('#editSupplierContactPhone').val(contactPhone);
    $('#editSupplierEmail').val(email);
    $('#editSupplierAddress').val(address);
    $('#editSupplierIsActive').prop('checked', isActive);
    $('#editSupplierModal').modal('show');
}

// Show delete confirmation modal
function confirmDelete(type, id, name) {
    $('#deleteId').val(id);
    $('#deleteItemName').text(name);
    
    // Set the form action based on type
    let action = '';
    switch (type) {
        case 'department':
            action = '?action=delete_department&tab=departments';
            break;
        case 'category':
            action = '?action=delete_category&tab=categories';
            break;
        case 'supplier':
            action = '?action=delete_supplier&tab=suppliers';
            break;
    }
    $('#deleteForm').attr('action', action);
    
    $('#deleteModal').modal('show');
}

// Initialize DataTables
$(document).ready(function() {
    $('#departmentsTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [{ orderable: false, targets: [5] }]
    });
    
    $('#categoriesTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [{ orderable: false, targets: [2] }]
    });
    
    $('#suppliersTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [{ orderable: false, targets: [5] }]
    });
    
    // Activate the current tab from URL
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
        $(`#${tab}-tab`).tab('show');
    }

    $('#txnType').change(function() {
        if ($(this).val() === 'transfer') {
            $('#transferDeptRow').show();
            $('#txnTransferTo').prop('required', true);
        } else {
            $('#transferDeptRow').hide();
            $('#txnTransferTo').prop('required', false);
        }
    });

    // Initialize DataTable
    $('#transactionsTable').DataTable({
        "pageLength": 25,
        "order": [[0, 'desc']], // Sort by date descending
        "columnDefs": [
            { "orderable": false, "targets": [8] } // Disable sorting on actions column
        ]
    });

    $('#drugsTable').DataTable({
        "pageLength": 25,
        "order": [[0, 'asc']], // Sort by drug name
        "columnDefs": [
            { "orderable": false, "targets": [6] }
        ]
    });

  // Initialize datepicker for expiry date
    const today = new Date();
    const minDate = today.toISOString().split('T')[0];
    $('#restockExpiryDate').attr('min', minDate);
    
    // Generate a simple batch number if empty
    $('#restockBatchNumber').on('focus', function() {
        if (!this.value) {
            const randomNum = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
            this.value = 'BATCH-' + randomNum + '-' + today.getFullYear().toString().slice(-2);
        }
    });

});