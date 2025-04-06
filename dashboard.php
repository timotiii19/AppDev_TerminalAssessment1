<?php

session_start();

$activeTab = 'dashboard'; // ✅ default

if (isset($_SESSION['redirect_to_expense_tab']) && $_SESSION['redirect_to_expense_tab'] === true) {
    $activeTab = 'addExpense';
    unset($_SESSION['redirect_to_expense_tab']);
} elseif (isset($_SESSION['active_tab'])) {
    $activeTab = $_SESSION['active_tab'];
    unset($_SESSION['active_tab']);
}

require 'config.php';

$expenses = []; 

// Redirect if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id']; // Get logged-in user's ID

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require 'config.php'; // Ensure this connects to your database

    
    // ADD CATEGORY
    $message = ""; // Default: No message

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_category"])) {
        $category_name = trim($_POST["category_name"]);
    
        if (!empty($category_name) && !empty($user_id)) {
            $stmt = $pdo->prepare("INSERT INTO categories (name, user_id) VALUES (:name, :user_id)");
            $stmt->bindParam(":name", $category_name, PDO::PARAM_STR);
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
    
            if ($stmt->execute()) {
                $_SESSION['redirect_to_expense_tab'] = true; // ✅ set redirect flag
                header("Location: dashboard.php");
                exit(); // ✅ important to stop script execution
            }
        }
    }
    

    

    // EDIT CATEGORY
    if (isset($_POST['edit_category'])) {
        $category_id = $_POST['category_id'];
        $new_category_name = trim($_POST['category_name']);    
    
        if (!empty($category_id) && !empty($new_category_name)) {
            $stmt = $pdo->prepare("UPDATE categories SET name = :name WHERE id = :id");
            $stmt->bindParam(":name", $new_category_name, PDO::PARAM_STR);
            $stmt->bindParam(":id", $category_id, PDO::PARAM_INT);
    
            if ($stmt->execute()) {
                $_SESSION['redirect_to_expense_tab'] = true;
                header("Location: dashboard.php");
                exit();
            } else {
                echo "Error updating category.";
            }
        } else {
            echo "<div class='alert alert-danger'>Category ID and name cannot be empty.</div>";
        }
    }
    
    
    
    


   // DELETE CATEGORY
   if (isset($_POST['delete_category'])) {
    $category_id = $_POST['category_id'] ?? null;

    if (!empty($category_id)) {
        // Step 1: Check if the category to be deleted is 'Uncategorized'
        $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = :category_id AND user_id = :user_id");
        $stmt->bindParam(":category_id", $category_id, PDO::PARAM_INT);
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($category['name'] === 'Uncategorized') {
            // Prevent deletion of 'Uncategorized' category and set message
            $_SESSION['message'] = "You cannot delete the 'Uncategorized' category.";
            $_SESSION['redirect_to_expense_tab'] = true; // Redirection after showing message
            header("Location: dashboard.php");
            exit();
        }

        // Step 2: Make sure "Uncategorized" exists, or create it if needed
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = 'Uncategorized' AND user_id = :user_id");
        $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $uncategorized = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$uncategorized) {
            $user_id = $_SESSION['user_id'];
            $stmt = $pdo->prepare("INSERT INTO categories (name, user_id) VALUES ('Uncategorized', :user_id)");
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->execute();
            $uncategorized_id = $pdo->lastInsertId();
        } else {
            $uncategorized_id = $uncategorized['id'];
        }

        // Step 3: Reassign all expenses from this category to "Uncategorized"
        $stmt = $pdo->prepare("UPDATE expenses SET category_id = :uncategorized_id WHERE category_id = :old_category_id");
        $stmt->execute([
            ':uncategorized_id' => $uncategorized_id,
            ':old_category_id' => $category_id
        ]);

        // Step 4: Now safely delete the category (if not 'Uncategorized')
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id");
        $stmt->bindParam(":id", $category_id, PDO::PARAM_INT);
        $stmt->execute();

        // Set session to trigger redirection to Add Expense tab
        $_SESSION['redirect_to_expense_tab'] = true;

        // Redirect to the same page to trigger the Add Expense tab
        header("Location: dashboard.php");
        exit();
    }
}

}



            // Fetch categories for dropdown (ensuring user-specific categories)
            try {
                $stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                die("Error fetching categories: " . $e->getMessage());
            }

            // Initialize query components
            $whereClauses = ["expenses.user_id = ?"];
            $params = [$user_id];

            // Sorting logic (Default sorting by purchase_date)
            $order_by = isset($_GET['sort_category']) && $_GET['sort_category'] !== '' ? 'categories.name' : 'expenses.purchase_date';  
            $order = isset($_GET['order']) && $_GET['order'] == 'asc' ? 'asc' : 'desc';  


            // Apply date filtering logic only if a date filter is set, else skip
            if (isset($_GET['all_expenses']) && $_GET['all_expenses'] == 'true') {
                // If "All" button is clicked, remove any date filters
            unset($_GET['sort_category'], $_GET['day_filter'], $_GET['week_filter'], $_GET['month_filter'], $_GET['year_filter']);
            } else {
                // Apply category filtering if category is selected
            if (isset($_GET['sort_category']) && $_GET['sort_category'] !== '') {
                $whereClauses[] = "category_id = ?";
                $params[] = $_GET['sort_category'];
            }
                // Apply day filter if it's set
                if (isset($_GET['day_filter']) && $_GET['day_filter'] !== '') {
                    $whereClauses[] = "DATE(expenses.purchase_date) = ?";
                    $params[] = $_GET['day_filter'];
                }

                // Week filtering logic (calculate start and end date for selected week)
                if (isset($_GET['week_filter']) && $_GET['week_filter'] !== '') {
                    // Get the selected week and year from the user input
                    $week = $_GET['week_filter']; // Example format: 2025-W14
                    $year = date('Y'); // Assume the current year for simplicity
                
                    // Extract the week number from the input string
                    $week_number = (int)substr($week, -2); // Get the last 2 digits (week number)
                
                    // Calculate the start date of the week
                    $startDate = new DateTime();
                    $startDate->setISODate($year, $week_number); // Pass the week number as an integer
                    $startDateFormatted = $startDate->format('Y-m-d');
                
                    // Calculate the end date of the week
                    $endDate = clone $startDate;
                    $endDate->modify('+6 days');
                    $endDateFormatted = $endDate->format('Y-m-d');
                
                    // Add date range filtering to the WHERE clauses
                    $whereClauses[] = "DATE(expenses.purchase_date) BETWEEN ? AND ?";
                    $params[] = $startDateFormatted;
                    $params[] = $endDateFormatted;
                }

                    // Apply month 
                    if (!empty($_GET['month_filter']) && empty($_GET['year_filter'])) {
                        $month = ltrim($_GET['month_filter'], '0'); // Convert "04" → "4"
                    
                        // Validate month (1-12)
                        if (ctype_digit($month) && $month >= 1 && $month <= 12) {
                            $whereClauses[] = "MONTH(expenses.purchase_date) = ?";
                            $params[] = (int) $month;
                    
                            // Convert month number to name
                            $monthName = date('F', mktime(0, 0, 0, $month, 1));
                            $filters[] = "Month: $monthName"; // Add to filter display
                        } else {
                            echo "<p style='color:red;'>Invalid month format.</p>";
                        }
                    }

                    // Apply month and year filtering
                    // If both month and year are selected
            if (!empty($_GET['month_filter']) && !empty($_GET['year_filter'])) {
                $month = ltrim($_GET['month_filter'], '0'); // Convert "04" → "4"
                $year = $_GET['year_filter'];

                // Validate both inputs
                if (ctype_digit($month) && $month >= 1 && $month <= 12 && ctype_digit($year) && strlen($year) === 4) {
                    $whereClauses[] = "MONTH(expenses.purchase_date) = ?";
                    $params[] = (int) $month;

                    $whereClauses[] = "YEAR(expenses.purchase_date) = ?";
                    $params[] = (int) $year;

                    // Display selected filters
                    $monthName = date('F', mktime(0, 0, 0, $month, 1));
                    $filters[] = "Month: $monthName";
                    $filters[] = "Year: $year";
                } else {
                    echo "<p style='color:red;'>Invalid month or year format.</p>";
                }
            } 

            $filters = [];

                    // Apply year-only filtering if set and month is not provided
                    if (!empty($_GET['year_filter'])) {
                        $year = $_GET['year_filter'];
                    
                        // Validate year (must be a 4-digit number)
                        if (ctype_digit($year) && strlen($year) === 4) {
                            $whereClauses[] = "YEAR(expenses.purchase_date) = ?";
                            $params[] = (int) $year;
                    
                            // **Prevent duplicate "Year" filter entry**
                            if (!in_array("Year: $year", $filters)) {
                                $filters[] = "Year: $year";
                            }
                        } else {
                            echo "<p style='color:red;'>Invalid year format.</p>";
                        }
                    }
                }

                $order_by = isset($_GET['sort']) ? $_GET['sort'] : 'purchase_date';
                $order_direction = (isset($_GET['direction']) && $_GET['direction'] == 'desc') ? 'DESC' : 'ASC';

                // Build the query with dynamic WHERE clauses
                $query = "SELECT expenses.*, categories.name AS category_name 
                        FROM expenses 
                        JOIN categories ON expenses.category_id = categories.id
                        WHERE " . implode(' AND ', $whereClauses) . "
                        ORDER BY " . ($order_by === 'category_name' ? 'categories.name' : 'expenses.' . $order_by) . " $order_direction";


                // Execute the query
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $whereSql = ""; // Default empty condition
                $params = []; // Ensure params array is initialized

                if (!empty($_POST['category'])) {
                    $whereSql = "WHERE categories.name = ?";
                    $params[] = $_POST['category'];
                }

                $totalExpense = 0; // Ensure it's always defined

                // Calculate total filtered expense
                $whereClauses = isset($whereClauses) && is_array($whereClauses) ? array_filter($whereClauses, 'is_string') : [];
                $params = isset($params) && is_array($params) ? $params : [];

                // Debugging: Check if whereClauses and params are set correctly
                $total_expense = 0; // Initialize to prevent undefined variable warning

                if (isset($expenses) && is_array($expenses)) {
                    foreach ($expenses as $expense) {
                        $total_expense += $expense['total_amount']; // Assuming 'total_amount' is the correct key
                    }
                }


            // Render the page (HTML)

            function getCategoryNameById($category_id, $categories) {
                foreach ($categories as $category) {
                    if ($category['id'] == $category_id) {
                        return $category['name'];
                    }
                }
                return 'Unknown'; // Return a default if the category is not found
            }

            // Fetch filtered expenses by date
            $filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
            $filtered_expenses = [];

            try {
                $query = "SELECT purchase_date, total_amount FROM expenses WHERE user_id = ?";
                $filtered_params = [$user_id];

                if (!empty($filter_date)) {
                    $query .= " AND purchase_date = ?";
                    $filtered_params[] = $filter_date;
                }

                $query .= " ORDER BY $order_by $order_direction";
                $stmt = $pdo->prepare($query);
                $stmt->execute($filtered_params);
                $filtered_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                echo "Error: " . $e->getMessage();
            }


?>

<!DOCTYPE html>
<html lang="en">
    <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Expense Manager Dashboard</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    </head>
    <body>
    <div class="container-fluid tight-top">

<!-- Tabs + Header + Logout inside a bordered wrapper -->
<div class="tab-wrapper p-3" style="border: 7px solid #d6c7b0; border-radius: 20px; background-color: #fffaf1;">

    <!-- Header + Logout -->
    <div class="d-flex justify-content-between align-items-center mb-1 position-relative px-3 pt-2">
    <h2 class="m-1 position-absolute start-50 translate-middle-x">
        <span class="header-box">Expense Tracker</span>
    </h2>
        <button class="btn btn-cream btn-sm ms-auto" onclick="location.href='login.php?logout=true'">
            Logout
        </button>
    </div>

    <!-- Tab Buttons -->
   <div class="tab-border-wrapper mb-3 p-2 rounded">
    <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php if ($activeTab == 'dashboard') echo 'active'; ?>" 
                    id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" 
                    type="button" role="tab">
                Dashboard
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php if ($activeTab == 'addExpense') echo 'active'; ?>"
                    id="addExpense-tab" data-bs-toggle="tab" data-bs-target="#addExpense" 
                    type="button" role="tab">
                Add an Expense
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php if ($activeTab == 'lineGraph') echo 'active'; ?>" 
                    id="lineGraph-tab" data-bs-toggle="tab" data-bs-target="#lineGraph" 
                    type="button" role="tab">
                Line Graph
            </button>
        </li>
    </ul>

            <!-- Tab Content -->
            <div class="tab-content mt-2" id="dashboardTabsContent">

            <!-- Dashboard -->
            <div class="tab-pane fade <?php if ($activeTab == 'dashboard') echo 'show active'; ?>" 
            id="dashboard" role="tabpanel">
                <h2>Dashboard Table</h2>

                <?php
                $filters = [];

                // Category Filter
                if (!empty($_GET['sort_category']) && $_GET['sort_category'] !== 'all') {
                    // Fetch category name from database
                    $category_id = $_GET['sort_category'];
                    $stmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
                    $stmt->execute([$category_id]);
                    $category_name = $stmt->fetchColumn();
                    if ($category_name) {
                        $filters[] = "Category: " . htmlspecialchars($category_name);
                    }
                }

                // Day Filter
                if (!empty($_GET['day_filter'])) {
                    $filters[] = "Day: " . date("F j, Y", strtotime($_GET['day_filter']));
                }

                // Week Filter (Convert week number to date range)
                if (!empty($_GET['week_filter'])) {
                    $week = $_GET['week_filter']; // Format: "2025-W15"
                    list($year, $weekNumber) = explode("-W", $week);

                    // Calculate start and end dates of the selected week
                    $startDate = date("Y-m-d", strtotime($year . "W" . $weekNumber . "1")); // Monday of the week
                    $endDate = date("Y-m-d", strtotime($year . "W" . $weekNumber . "7")); // Sunday of the week

                    $filters[] = "Week: $startDate to $endDate";
                }


                       // Month filter (if selected)
                       if (!empty($_GET['month_filter']) && empty($_GET['year_filter'])) {
                        $month = ltrim($_GET['month_filter'], '0'); // Convert "04" → "4"
                    
                        // Validate month (1-12)
                        if (ctype_digit($month) && $month >= 1 && $month <= 12) {
                            $whereClauses[] = "MONTH(expenses.purchase_date) = ?";
                            $params[] = (int) $month;
                    
                            // Convert month number to name
                            $monthName = date('F', mktime(0, 0, 0, $month, 1));
                            $filters[] = "Month: $monthName"; // Add to filter display
                        } else {
                            echo "<p style='color:red;'>Invalid month format.</p>";
                        }
                    }

                  
                    // If both month and year are selected
                    if (!empty($_GET['month_filter']) && !empty($_GET['year_filter'])) {
                        $month = ltrim($_GET['month_filter'], '0'); // Convert "04" → "4"
                        $year = $_GET['year_filter'];

                        // Validate both inputs
                        if (ctype_digit($month) && $month >= 1 && $month <= 12 && ctype_digit($year) && strlen($year) === 4) {
                            $whereClauses[] = "MONTH(expenses.purchase_date) = ?";
                            $params[] = (int) $month;

                            $whereClauses[] = "YEAR(expenses.purchase_date) = ?";
                            $params[] = (int) $year;

                            // Display selected filters
                            $monthName = date('F', mktime(0, 0, 0, $month, 1));
                            $filters[] = "Month: $monthName";
                            $filters[] = "Year: $year";
                        } else {
                            echo "<p style='color:red;'>Invalid month or year format.</p>";
                        }
                    } 


                // Year Filter
                if (!empty($_GET['year_filter'])) {
                    $year = $_GET['year_filter'];
                
                    // Validate year (must be a 4-digit number)
                    if (ctype_digit($year) && strlen($year) === 4) {
                        $whereClauses[] = "YEAR(expenses.purchase_date) = ?";
                        $params[] = (int) $year;
                
                        // **Prevent duplicate "Year" filter entry**
                        if (!in_array("Year: $year", $filters)) {
                            $filters[] = "Year: $year";
                        }
                    } else {
                        echo "<p style='color:red;'>Invalid year format.</p>";
                    }
                }

                // Display filter message
                if (!empty($filters)) {
                    echo '<p class="alert alert-cream"><strong>Filtered By:</strong> ' . implode(" | ", $filters) . '</p>';
                } else {
                    echo '<p class="alert alert-cream">Showing All Expenses</p>';
                }
                ?>
            

                <table class="table table-striped">
                            

            <!-- Sort and Filter Form -->
            <form method="GET" class="mb-3 d-flex align-items-center flex-wrap gap-2">
                <div class="d-flex gap-2">
                    
                <!-- All Button to Show All Expenses (No Date Filter) -->
                <button type="submit" class="btn btn-cream btn-sm" name="all_expenses" value="true">All</button>

                <!-- Category Sorting Button -->
                <select name="sort_category" class="form-select select-cream" onchange="this.form.submit()">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category) { ?>
                        <option value="<?= $category['id'] ?>" 
                            <?= isset($_GET['sort_category']) && $_GET['sort_category'] == $category['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['name']) ?>
                        </option>
                    <?php } ?>
                </select>

                <div class="d-flex gap-1">
                <!-- Filter by Date -->
                <div class="dropdown">
                <button class="btn btn-cream btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Day</button>
                    <div class="dropdown-menu p-2">
                        <input type="date" class="form-control" name="day_filter" id="day-input" onchange="this.form.submit()">
                    </div>
                </div>

                <!-- Filter by Week -->
                <div class="dropdown">
                <button class="btn btn-cream btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Week</button>
                    <div class="dropdown-menu p-2">
                        <input type="week" class="form-control" name="week_filter" id="week-input" onchange="this.form.submit()">
                    </div>
                </div>

                <!-- Filter by Month -->
                <div class="dropdown">
                <button class="btn btn-cream btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Month
                    </button>
                    <div class="dropdown-menu p-2">
                        <select class="form-select" name="month_filter" id="month-input" onchange="this.form.submit()">
                            <option value="">Select Month</option>
                            <?php 
                            for ($m = 1; $m <= 12; $m++) { 
                                $monthValue = str_pad($m, 2, '0', STR_PAD_LEFT); // Format "01", "02", etc.
                                $selected = (isset($_GET['month_filter']) && $_GET['month_filter'] == $monthValue) ? 'selected' : '';
                            ?>
                                <option value="<?= $monthValue ?>" <?= $selected ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <!-- Filter by Year -->
                <div class="dropdown">
                <button class="btn btn-cream btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Year</button>
                    <div class="dropdown-menu p-2">
                        <select class="form-select" name="year_filter" id="year-input" onchange="this.form.submit()">
                            <option value="">Select Year</option>
                            <?php 
                            $currentYear = date("Y");
                            for ($y = $currentYear; $y >= $currentYear - 10; $y--) { ?>
                                <option value="<?= $y ?>" 
                                    <?= isset($_GET['year_filter']) && $_GET['year_filter'] == $y ? 'selected' : '' ?>>
                                    <?= $y ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </form>

            
       <!-- Assuming $expenses is the array of expense records -->
       <table class="table table-bordered table-hover table-sm">

                <thead>
                    <tr>
                    <th scope="col" class="th-cream">Category</th>
                    <th scope="col" class="th-cream">Description</th>
                    <th scope="col" class="th-cream">Total Amount</th>
                    <th scope="col" class="th-cream">Quantity</th>
                    <th scope="col" class="th-cream">Amount Per Piece</th>
                    <th scope="col" class="th-cream">Payment Method</th>
                    <th scope="col" class="th-cream">Date</th>
                    <th scope="col" class="th-cream">Action</th> 
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $expense): ?>
                    <tr>
                    <td class="text-center"><?= htmlspecialchars(getCategoryNameById($expense['category_id'], $categories)) ?></td>
                    <td class="text-center"><?= htmlspecialchars($expense['description']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($expense['total_amount']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($expense['quantity']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($expense['amount_per_piece']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($expense['payment_method']) ?></td>
                    <td class="text-center"><?= htmlspecialchars($expense['purchase_date']) ?></td>

                    <td>
                        <div class="text-center">
                            <!-- Cream Edit Button -->
                           <button 
                                class="btn btn-sm btn-cream"
                                data-bs-toggle="modal" 
                                data-bs-target="#editExpenseModal"
                                data-id="<?= $expense['id'] ?>"
                                data-category="<?= $expense['category_id'] ?>"
                                data-description="<?= htmlspecialchars($expense['description']) ?>"
                                data-amount="<?= $expense['total_amount'] ?>"
                                data-quantity="<?= $expense['quantity'] ?>"
                                data-payment="<?= $expense['payment_method'] ?>"
                                data-purchase="<?= date('Y-m-d', strtotime($expense['purchase_date'])) ?>"
                            >Edit</button>


                            <!-- Cream Outline Delete Button -->
                            <a href="delete.php?id=<?= $expense['id']; ?>&type=expense" class="btn btn-sm btn-cream">
                                Delete
                            </a>
                        </div>
                    </td>

                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Display Total Expenses Below the Table -->
            <h3 class="mt-1" style="font-size: 1.5rem;">Total Expenses: <?= number_format($total_expense, 2) ?></h3>

                
                   <!-- Standard Export PDF Form (GET) -->
                        <form method="GET" action="export_pdf.php" class="mt-2">
                            <!-- Filter inputs -->
                            <input type="hidden" name="day_filter" value="<?= htmlspecialchars($_GET['day_filter'] ?? '') ?>">
                            <input type="hidden" name="week_filter" value="<?= htmlspecialchars($_GET['week_filter'] ?? '') ?>">
                            <input type="hidden" name="month_filter" value="<?= htmlspecialchars($_GET['month_filter'] ?? '') ?>">
                            <input type="hidden" name="year_filter" value="<?= htmlspecialchars($_GET['year_filter'] ?? '') ?>">
                            <input type="hidden" name="sort_category" value="<?= htmlspecialchars($_GET['sort_category'] ?? '') ?>">

                            <div class="d-flex justify-content-center mt-1">
                                <button class="btn btn-cream btn-sm">Export Table</button>
                            </div>
                        </form>

                        <!-- Export PDF with Chart (POST) -->
                        <form id="exportWithChartForm" method="POST" action="export_pdf.php">
                            <input type="hidden" name="chart_image" id="chart_image">

                    
                            <!-- Duplicate filters to POST if needed -->
                            <input type="hidden" name="day_filter" value="<?= htmlspecialchars($_GET['day_filter'] ?? '') ?>">
                            <input type="hidden" name="week_filter" value="<?= htmlspecialchars($_GET['week_filter'] ?? '') ?>">
                            <input type="hidden" name="month_filter" value="<?= htmlspecialchars($_GET['month_filter'] ?? '') ?>">
                            <input type="hidden" name="year_filter" value="<?= htmlspecialchars($_GET['year_filter'] ?? '') ?>">
                            <input type="hidden" name="sort_category" value="<?= htmlspecialchars($_GET['sort_category'] ?? '') ?>">

                            <div class="d-flex justify-content-center mt-1">
                                <button class="btn btn-cream btn-sm">Export Table with Chart</button>
                            </div>
                        </form>
                    

                    </div>


            <!-- Add an Expense Tab -->
            <div class="tab-pane fade <?php if ($activeTab == 'addExpense') echo 'show active'; ?>" id="addExpense" role="tabpanel">
                    <h2 class="text-left mb-4">Add an Expense</h2>

                    <div class="d-flex justify-content-start align-items-center">
                        
    <!-- Manage Categories Button (opens modal) -->
    <div class="text-left mb-4">
        <button class="btn btn-cream btn-sm" data-bs-toggle="modal" data-bs-target="#manageCategoriesModal">Manage Categories</button>
    </div>

    <!-- Display message beside the button -->
    <?php
    if (isset($_SESSION['message'])) {
        echo "
        <div class='alert alert-warning mb-0 ms-3' id='message'>
            " . $_SESSION['message'] . "
        </div>
        <script>
            setTimeout(function() {
                document.getElementById('message').style.display = 'none'; // Hide after 5 seconds
            }, 5000);
        </script>
        ";
        unset($_SESSION['message']); // Clear message after it's displayed
    }
    ?>
</div>

            <form method="POST" action="add_expense.php">
                <div class="row g-10">
                <!-- Category -->
                <div class="col-md-6">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category" required>
                    <?php foreach ($categories as $category) { ?>
                        <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                    <?php } ?>
                    </select>
                </div>

                <!-- Description -->
                <div class="col-md-6">
                    <label for="description" class="form-label">Description</label>
                    <input type="text" class="form-control" id="description" name="description" required>
                </div>

                <div class="mb-4"></div>

                <!-- Total Amount -->
                <div class="col-md-6">
                    <label for="total_amount" class="form-label">Total Amount</label>
                    <input type="number" class="form-control" id="total_amount" name="total_amount" step="0.01" required>
                </div>

                <!-- Quantity -->
                <div class="col-md-6">
                    <label for="quantityField" class="form-label">Quantity</label>
                    <input type="number" name="quantity" class="form-control" id="quantityField" min="1" max="100">

                    <div class="form-check mt-2 d-flex justify-content-end">
                        <input type="checkbox" class="form-check-input me-2" id="naCheckbox" name="na" value="1">
                        <label class="form-check-label" for="naCheckbox">Not Applicable/Countable (N/A)</label>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="col-md-6">
                    <label for="payment_method" class="form-label">Payment Method</label>
                    <select class="form-select" id="payment_method" name="payment_method" required>
                    <option value="Cash">Cash</option>
                    <option value="Card">Card</option>
                    </select>
                </div>

                <!-- Purchase Date -->
                <div class="col-md-6">
                    <label for="purchase_date" class="form-label">Purchase Date</label>
                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" required>
                </div>
                </div>

                <div class="text-center mt-4">
                <button type="submit" class="btn btn-cream btn-sm">Add Expense</button>
                </div>
            </form>
            </div>



         <!-- Manage Categories Modal -->
<div class="modal fade" id="manageCategoriesModal" tabindex="-1" aria-labelledby="manageCategoriesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="manageCategoriesModalLabel">Manage Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Add New Category -->
                <form method="POST" action="dashboard.php">
                    <div class="mb-3">
                        <label for="category_name" class="form-label">New Category</label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    <button type="submit"  class="btn btn-sm btn-cream" name="add_category">Add Category</button>
                </form>

                
                        <hr>
                    <h5>Existing Categories</h5>
                        <ul class="list-group" id="categoryList">
                            <?php foreach ($categories as $category) { ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?= htmlspecialchars($category['name']) ?>
                                    <div class="btn-group">
                                        <!-- Edit Button -->
                                        <button class="btn btn-sm btn-cream"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editCategoryModal"
                                                data-category-id="<?= $category['id'] ?>"
                                                data-category-name="<?= htmlspecialchars($category['name']) ?>">
                                            Edit
                                        </button>

                                        <!-- Delete Category Form -->
                                        <form method="POST" action="dashboard.php">
                                            <input type="hidden" name="category_id" value="<?= $category['id']; ?>" />
                                            <button type="submit" name="delete_category" class="btn btn-sm btn-cream">Delete</button>
                                        </form>

                                    </div>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>


<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="dashboard.php">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-cream" name="edit_category">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>


            <!-- Edit Expense Modal -->
                    <div class="modal fade" id="editExpenseModal" tabindex="-1" aria-labelledby="editExpenseModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editExpenseModalLabel">Edit Expense</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="editExpenseForm" method="POST" action="edit_expense.php">
                                    <input type="hidden" name="expense_id" id="expense_id">


                                        <!-- Category -->
                                        <div class="mb-3">
                                            <label for="edit_expense_category" class="form-label">Category</label>
                                            <select class="form-select" name="edit_expense_category" id="edit_expense_category" required>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- Description -->
                                        <div class="mb-3">
                                            <label for="edit_expense_description" class="form-label">Description</label>
                                            <input type="text" class="form-control" name="edit_expense_description" id="edit_expense_description" required>
                                        </div>

                                        <!-- Total Amount -->
                                        <div class="mb-3">
                                            <label for="edit_expense_amount" class="form-label">Total Amount</label>
                                            <input type="number" class="form-control" name="edit_expense_amount" id="edit_expense_amount" required>
                                        </div>

                                        <!-- Quantity -->
                                        <div class="mb-3">
                                            <label for="edit_expense_quantity" class="form-label">Quantity</label>
                                            <input type="number" class="form-control" name="edit_expense_quantity" id="edit_expense_quantity" required>
                                        </div>

                                        <!-- Payment Method -->
                                        <div class="mb-3">
                                            <label for="edit_expense_payment" class="form-label">Payment Method</label>
                                            <select class="form-select" name="edit_expense_payment" id="edit_expense_payment" required>
                                                <option value="Cash">Cash</option>
                                                <option value="Card">Card</option>
                                            </select>
                                        </div>

                                        <!-- Purchase Date -->
                                        <div class="mb-3">
                                            <label for="purchase_date">Purchase Date:</label>
                                            <input type="date" id="edit_purchase_date" name="edit_purchase_date" required>
                                        </div>

                                        <button type="submit" class="btn btn-sm btn-cream">Save Changes</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>


            <!-- Tab Content -->
            <div class="tab-pane fade <?php if ($activeTab == 'lineGraph') echo 'show active'; ?>" 
                    id="lineGraph" role="tabpanel" style="background-color: #fff8e6; padding: 20px; border-radius: 10px;">
                    
                    <div style="height: 400px; background: #fff8e6; padding: 20px;">
                        <canvas id="lineGraphCanvas"></canvas>
                    </div>
                </div>

                <!-- Chart.js for Line Graph -->
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                
                   <script>
document.addEventListener("DOMContentLoaded", function () {
    const expenses = <?php echo json_encode($expenses); ?>;
    let lineGraph;
    let chartRendered = false;

    function generateGraph(data) {
        const canvas = document.getElementById("lineGraphCanvas");
        if (!canvas) return;

        const ctx = canvas.getContext("2d");
        if (lineGraph) lineGraph.destroy();

        lineGraph = new Chart(ctx, {
            type: "line",
            data: {
                labels: data.map(e => e.purchase_date),
                datasets: [{
                    label: "Expenses",
                    data: data.map(e => e.total_amount),
                    borderColor: "#8B4513",
                    borderWidth: 2,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    onComplete: () => {
                        chartRendered = true;
                    }
                }
            }
        });
    }

    // 🕶️ Temporarily show the tab-pane to allow rendering
    const graphPane = document.getElementById("lineGraph");
    let originallyHidden = false;

    if (graphPane && graphPane.classList.contains("fade") && !graphPane.classList.contains("show")) {
        originallyHidden = true;
        graphPane.classList.add("show", "active");
    }

    // Wait for layout before generating chart
    setTimeout(() => {
        generateGraph(expenses);

        // 🧼 Restore original hidden state
        if (originallyHidden) {
            graphPane.classList.remove("show", "active");
        }
    }, 100); // small delay ensures layout is stable

    // 📤 Export with chart
    const form = document.getElementById("exportWithChartForm");
    const chartInput = document.getElementById("chart_image");

    if (form && chartInput) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            function tryExport() {
                if (!chartRendered) {
                    return setTimeout(tryExport, 100);
                }

                const canvas = document.getElementById("lineGraphCanvas");
                if (!canvas) return;

                const exportCanvas = document.createElement("canvas");
                exportCanvas.width = 1000;
                exportCanvas.height = 400;

                const ctx = exportCanvas.getContext("2d");
                ctx.fillStyle = "#ffffff";
                ctx.fillRect(0, 0, exportCanvas.width, exportCanvas.height);

                ctx.drawImage(canvas, 0, 0, exportCanvas.width, exportCanvas.height);

                const imgData = exportCanvas.toDataURL("image/jpeg", 1.0);
                chartInput.value = imgData;

                form.submit();
            }

            tryExport();
        });
    }
});
</script>



                        <script>
                        document.addEventListener("DOMContentLoaded", function () {
                            const editModal = document.getElementById("editExpenseModal");
                            editModal.addEventListener("show.bs.modal", function (event) {
                                let button = event.relatedTarget; // Button that triggered the modal
                                let expenseId = button.getAttribute("data-id");

                                console.log("Expense ID:", expenseId); // Debugging - check in console

                                document.getElementById("expense_id").value = expenseId;
                                document.getElementById("edit_expense_category").value = button.getAttribute("data-category");
                                document.getElementById("edit_expense_description").value = button.getAttribute("data-description");
                                document.getElementById("edit_expense_amount").value = button.getAttribute("data-amount");
                                document.getElementById("edit_expense_quantity").value = button.getAttribute("data-quantity");
                                document.getElementById("edit_expense_payment").value = button.getAttribute("data-payment");
                                document.getElementById("edit_purchase_date").value = button.getAttribute("data-purchase");

                            });
                        });
                        </script>


                    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

                    <script>
                        document.addEventListener("DOMContentLoaded", function () {
                            var triggerTabList = [].slice.call(document.querySelectorAll('#dashboardTabs a'));
                            triggerTabList.forEach(function (triggerEl) {
                                triggerEl.addEventListener('click', function (event) {
                                    event.preventDefault();
                                    var tab = new bootstrap.Tab(triggerEl);
                                    tab.show();
                                });
                            });
                        });
                    </script>

                    <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const editButtons = document.querySelectorAll('[data-bs-target="#editCategoryModal"]');

                        editButtons.forEach(button => {
                            button.addEventListener('click', function () {
                                const id = this.getAttribute('data-category-id');
                                const name = this.getAttribute('data-category-name');

                                document.getElementById('edit_category_id').value = id;
                                document.getElementById('edit_category_name').value = name;
                            });
                        });
                    });
                    </script>

<script>
    // Toggle quantity field when "N/A" is checked
    const naCheckbox = document.getElementById('naCheckbox');
    const quantityField = document.getElementById('quantityField');

    naCheckbox.addEventListener('change', function () {
        quantityField.disabled = this.checked;
        if (this.checked) {
            quantityField.value = '';
        }
    });
</script>

<script src="https://unpkg.com/lucide@latest"></script>


</body>
</html>

<style>
       

       body {
            font-family: 'Poppins', sans-serif;
            background-color: rgb(244, 236, 196); /* your intended background */
            margin: 0;
            padding: 0;
            min-height: 100vh;
            }

        .tight-top {
            margin-top: 20px; /* or 0 */
        }
        .header-box {
            border: 2px solid #5c4438; /* Deep brown for seriousness */
            background-color: #f5e1c0; /* Soft cream for contrast */
            padding: 10px 30px;
            display: inline-block;
            border-radius: 10px;
            font-weight: bold;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .th-cream {
            background-color:rgb(206, 191, 165) !important;
            color: #5c4438 !important;
            border: 1px solidrgb(92, 83, 74) !important;
            text-align: center;
            }
        .btn-cream {
            background-color: #f5e1c0; /* light cream with a hint of brown */
            color: #5c4438;            /* deeper brown for text */
            border: 1px solid #d6b89c;
        }

        .btn-cream:hover {
            background-color: #ecd3ad;
            color: #3f2e24;
        }

        .btn-cream-outline {
            background-color: transparent;
            color: #5c4438;
            border: 1px solid #d6b89c;
        }

        .btn-cream-outline:hover {
            background-color: #f5e1c0;
            color: #3f2e24;
        }

        .alert-cream {
            background-color:rgb(246, 243, 237);
            color: #5c4438;
            border: 1px solid #e6c8a6;
        }
        
        .nav-tabs .nav-link {
            background-color:rgb(244, 235, 220);
            color: #5c4438;
            border: 1px solidrgb(103, 74, 47);
            margin-right: 4px;
        }

        .nav-tabs .nav-link.active {
            background-color:rgb(150, 120, 82);
            color:rgb(255, 255, 255);
            border-color:rgb(109, 81, 56) #d6b89c #fff;
        }

        .nav-tabs {
            border-bottom: 1px solidrgb(255, 255, 255);
            margin-bottom: 20px;
        }

        .tab-pane#lineGraph {
            background-color:rgb(220, 207, 178);
            padding: 20px;
            border-radius:10px;
        }

        .container {
            width: 90%;
            max-width: none;
            padding: 30px;
            margin-top: 20px;
            margin-bottom: 40px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            }

        #filterContainer, .table-container {
            margin-top: 20px;
            margin-bottom: 20px;
        }

        html, body {
            overflow-x: hidden;  /* prevent sideways scroll */
            overflow-y: 95%;    /* allow scroll only when needed */
            height: 95%;
        }
        .tab-pane {
            overflow-y: 95%;
            max-height: calc(100vh - 200px); /* or any reasonable size */
            padding-bottom: 20px;
        }

        #lineGraphCanvas {
            max-width: 100%;
            height: auto;
        }
    
        #expenseChartContainer {
            width: 100%;
            height: auto;
            overflow: visible;
        }


            h2 {
                margin-top: 10px;
                margin-bottom: 10px;
            }

        .title {
            text-align: center;
            margin-bottom: 20px;
        }
        .dashboard {
            flex-grow: 1;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            height: 100%; /* Make it take full height */
            padding-top: 0px; /* Add space between the button and table */
        }

        .table-container {
            flex-grow: 1;
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse; /* removes spacing between cells */
            border: 2px solidrgb(235, 207, 182); /* outer border */
            }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solidrgb(87, 69, 53);
        }
        .sort-btn {
            padding: 10px;
            background: #007BFF;
            color: white;
            border: none;
            cursor: pointer;
        }
        .controls {
            display: flex;
            flex-direction: column; /* Stack elements vertically */
            gap: 10px; /* Add spacing between elements */
            align-items: flex-start; /* Align items to the left */
        }

        .date-filter-container {
            display: flex;
            flex-direction: column;
            align-items: flex-start; /* Prevent horizontal stretching */
            gap: 5px; /* Space between dropdown and date picker */
        }

        #specific-date {
            width: auto; /* Adjust width automatically based on content */
            min-width: 100px; /* Ensure it has a reasonable minimum width */
            padding: 1px;
        }
        .add-btn {
            margin-bottom: 20px; /* Adjust the value for more or less space */
        }

        button, .btn {
                margin-top: 5px;
                margin-bottom: 0px;
            }

            /* Modal Header */
            .modal-header {
                background-color: #f5e1c0;
                color: #5c4438;
                border-bottom: 1px solid #d6b89c;
            }

            /* Modal Body */
            .modal-body {
                background-color: #fff8e6;
                color: #5c4438;
            }

            /* Modal Footer Buttons (if any) */
            .modal-footer .btn {
                background-color: #f5e1c0;
                color: #5c4438;
                border: 1px solid #d6b89c;
            }

            .modal-footer .btn:hover {
                background-color: #ecd3ad;
                color: #3f2e24;
            }

            .select-cream {
                background-color: #f5e1c0;
                color: #5c4438;
                border: 1px solid #d6b89c;
            }

            .select-cream:focus {
                border-color: #a58b6f;
                box-shadow: 0 0 0 0.2rem rgba(165, 139, 111, 0.25);
            }

            /* Uniform height and styling for all filter inputs/buttons */
           
            .select-cream,
            .form-control,
            .btn-cream,
            .dropdown-toggle {
                height: 38px; /* Uniform height */
                vertical-align: middle;
                margin: 0;
                display: inline-flex;
                align-items: center; 
            }

            /* Optional: Match dropdown menu input style inside filters */
            .dropdown-menu .form-control,
            .dropdown-menu .form-select {
                height: 32px;
                font-size: 14px;
                padding: 4px 8px;
            }
</style>