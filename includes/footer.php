
        </div>
    </div>

    <footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            <span class="text-muted">Mulago Pharmacy Inventory System &copy; <?= date('Y') ?></span>
        </div>
    </footer>

<!-- jQuery (full version) -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>

<!-- Bootstrap 4 Bundle JS (includes Popper) -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize all modals and functions
    var PharmacyModals = {
        // Inventory Modals
        showRestockModal: function(drugId, drugName) {
            $("#restockDrugId").val(drugId);
            $("#drugName").val(drugName);
            $("#restockModal").modal("show");
        },
        
showAdjustModal: function(inventoryId, drugName, batchNumber, expiryDate) {
    $("#adjustInventoryId").val(inventoryId);
    $("#adjustDrugName").val(drugName);
    $("#adjustBatchNumber").val(batchNumber);
    $("#adjustExpiryDate").val(expiryDate);
    $("#adjustNewExpiryDate").val("").attr('min', new Date().toISOString().split('T')[0]);
    $("#adjustNotes").val("");
    $("#adjustment").val(1);
    $("#adjustmentType").val("1").trigger('change');
    $("#adjustModal").modal("show");
},
        
        showTransferModal: function(inventoryId, drugName, currentStock) {
            $("#transferInventoryId").val(inventoryId);
            $("#transferDrugName").val(drugName);
            $("#transferQuantity").attr("max", currentStock).val(1);
            $("#currentStock").text(currentStock);
            $("#toDepartment").val("");
            $("#transferNotes").val("");
            $("#transferModal").modal("show");
        },
        
        // User Modals
        showUserEditModal: function(userId, username, fullName, email, departmentId, role, isActive) {
            $("#editUserId").val(userId);
            $("#editUsername").val(username);
            $("#editFullName").val(fullName);
            $("#editEmail").val(email);
            $("#editDepartment").val(departmentId || "");
            $("#editRole").val(role);
            $("#editIsActive").prop("checked", isActive);
            $("#editUserModal").modal("show");
        },
        
        showPasswordModal: function(userId, username) {
            $("#passwordUserId").val(userId);
            $("#passwordUsername").val(username);
            $("#newPassword").val("");
            $("#passwordModal").modal("show");
        },
        
        // Drug Modals
        showDrugEditModal: function(drugId, drugName, genericName, categoryId, unitOfMeasure, reorderLevel, description, isActive) {
            $('#editDrugId').val(drugId);
            $('#editDrugName').val(drugName);
            $('#editGenericName').val(genericName);
            $('#editCategory').val(categoryId || '');
            $('#editUnit').val(unitOfMeasure);
            $('#editReorderLevel').val(reorderLevel);
            $('#editDescription').val(description || '');
            $('#editIsActive').prop('checked', isActive);
            $('#editDrugModal').modal('show');
        },
        
        // Department Modals
        showDepartmentEditModal: function(id, name, location, contactPerson, contactPhone, isActive) {
            $('#editDeptId').val(id);
            $('#editDeptName').val(name);
            $('#editDeptLocation').val(location);
            $('#editDeptContactPerson').val(contactPerson);
            $('#editDeptContactPhone').val(contactPhone);
            $('#editDeptIsActive').prop('checked', isActive);
            $('#editDepartmentModal').modal('show');
        },
        
        // Category Modals
        showCategoryEditModal: function(id, name, description) {
            $('#editCatId').val(id);
            $('#editCatName').val(name);
            $('#editCatDescription').val(description);
            $('#editCategoryModal').modal('show');
        },
        
        // Supplier Modals
        showSupplierEditModal: function(id, name, contactPerson, contactPhone, email, address, isActive) {
            $('#editSupplierId').val(id);
            $('#editSupplierName').val(name);
            $('#editSupplierContactPerson').val(contactPerson);
            $('#editSupplierContactPhone').val(contactPhone);
            $('#editSupplierEmail').val(email);
            $('#editSupplierAddress').val(address);
            $('#editSupplierIsActive').prop('checked', isActive);
            $('#editSupplierModal').modal('show');
        },
        
        // Delete Modals
        showDeleteModal: function(type, id, name) {
            $('#deleteId').val(id);
            $('#deleteItemName').text(name);
            
            let action = '';
            switch (type) {
                case 'user':
                    action = '?action=delete_user';
                    break;
                case 'drug':
                    action = '?action=delete_drug';
                    break;
                case 'transaction':
                    action = '?action=delete_transaction';
                    break;
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
        },
        
        showRemoveExpiringModal: function(inventoryId, drugName, currentStock) {
            $("#removeExpiringInventoryId").val(inventoryId);
            $("#removeExpiringDrugName").val(drugName);
            $("#removeExpiringQuantity").attr("max", currentStock).val(1);
            $("#currentExpiringStock").text(currentStock);
            $("#removeExpiringModal").modal("show");
        }

        
    };

$("#adjustmentType").change(function() {
    if ($(this).val() == "1") {
        $("#adjustNewExpiryDate").closest('.form-group').show();
    } else {
        $("#adjustNewExpiryDate").closest('.form-group').hide();
    }
}).trigger('change');

    function updateStockInfo() {
        const drugId = $('select[name="drug_id"]').val();
        const departmentId = $('select[name="department_id"]').val();
        
        if (drugId && departmentId) {
            $.ajax({
                url: '../api/getStock.php',
                method: 'GET',
                data: {
                    drug_id: drugId,
                    department_id: departmentId
                },
                success: function(data) {
                    if (data.error) {
                        $('.available-stock').text(data.error).addClass('text-danger');
                        return;
                    }
                    
                    const stock = data.stock || 0;
                    const stockElement = $('.available-stock');
                    stockElement.text(stock);
                    
                    $('input[name="quantity"]').not('#restockQuantity').attr('max', stock);
                    
                    stockElement.removeClass('text-danger text-warning text-success');
                    
                    if (stock <= 0) {
                        stockElement.addClass('text-danger');
                    } else if (stock < 10) {
                        stockElement.addClass('text-warning');
                    } else {
                        stockElement.addClass('text-success');
                    }
                },
                error: function() {
                    $('.available-stock').text('Error loading stock info').addClass('text-danger');
                }
            });
        } else {
            $('.available-stock').text('Select drug and department first').removeClass('text-danger text-warning text-success');
        }
    }
    
    // Call update when either dropdown changes
    $('select[name="drug_id"], select[name="department_id"]').change(updateStockInfo);
    
    // Also call when modal is shown
    $('#addTransactionModal').on('shown.bs.modal', updateStockInfo);

    $('#restockForm').on('submit', function(e) {
        const quantity = parseInt($('#restockQuantity').val());
        if (quantity <= 0) {
            alert('Quantity must be at least 1');
            e.preventDefault();
            return false;
        }
        return true;
    });

    // Initialize datepicker for expiry date
    $("#expiryDate").attr("min", new Date().toISOString().split("T")[0]);

    // Generate a simple batch number if empty
    $("#batchNumber").on("focus", function() {
        if (!this.value) {
            this.value = "BATCH-" + Math.floor(Math.random() * 10000).toString().padStart(4, "0");
        }
    });

    // Update adjustment value based on type
    $("#adjustmentType").change(function() {
        var currentVal = $("#adjustment").val();
        if (currentVal < 0) {
            $("#adjustment").val(Math.abs(currentVal));
        }
    });

    // Transfer department toggle
    $('#txnType').change(function() {
        if ($(this).val() === 'transfer') {
            $('#transferDeptRow').show();
            $('#txnTransferTo').prop('required', true);
        } else {
            $('#transferDeptRow').hide();
            $('#txnTransferTo').prop('required', false);
        }
    });

    // Initialize datepicker for restock expiry date
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

    // Initialize all DataTables
    function initializeDataTables() {
        $('#inventoryTable').DataTable({
            pageLength: 25,
            order: [[2, "asc"], [0, "asc"]],
            columnDefs: [{ orderable: false, targets: [6] }]
        });

        $('#usersTable').DataTable({
            pageLength: 25,
            order: [[4, "asc"], [1, "asc"]],
            columnDefs: [{ orderable: false, targets: [7] }]
        });

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

        $('#transactionsTable').DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            columnDefs: [{ orderable: false, targets: [8] }]
        });

        $('#drugsTable').DataTable({
            pageLength: 25,
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: [6] }]
        });
    }

    // Activate the current tab from URL
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
        $(`#${tab}-tab`).tab('show');
    }

    // Transaction type toggle
    $('#txnType').change(function() {
        if ($(this).val() === 'transfer') {
            $('#transferDeptRow').show();
            $('#txnTransferTo').prop('required', true);
            $('#salePriceRow').hide();
            $('#txnUnitPrice').prop('required', false);
        } else if ($(this).val() === 'sale') {
            $('#transferDeptRow').hide();
            $('#txnTransferTo').prop('required', false);
            $('#salePriceRow').show();
            $('#txnUnitPrice').prop('required', true);
        } else {
            $('#transferDeptRow').hide();
            $('#txnTransferTo').prop('required', false);
            $('#salePriceRow').hide();
            $('#txnUnitPrice').prop('required', false);
        }
    });

    // Calculate total amount when quantity or unit price changes
    $('#txnQuantity, #txnUnitPrice').on('input', function() {
        if ($('#txnType').val() === 'sale') {
            const qty = parseFloat($('#txnQuantity').val()) || 0;
            const price = parseFloat($('#txnUnitPrice').val()) || 0;
            $('#txnTotalAmount').val((qty * price).toFixed(2));
        }
    });

    // Initialize everything
    initializeDataTables();

    // Make the modal functions available globally
    window.PharmacyModals = PharmacyModals;
});
</script>
</body>
</html>