<?php

session_start();

require_once "../config/database.php";

// Make sure user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Get all meal groups where the current user is a member
$stmt = $pdo->prepare("
    SELECT 
        mg.id,
        mg.name,
        mg.join_code,
        mg.month_name,
        mg.year,
        mm.role
    FROM meal_members mm
    INNER JOIN meal_groups mg 
        ON mm.meal_group_id = mg.id
    WHERE mm.user_id = ?
    ORDER BY mg.created_at DESC
");

$stmt->execute([$user_id]);

$meal_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard - Meal System</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>

        /* ================================
           DASHBOARD
        ================================= */

        .dashboard-page {
            min-height: 100vh;
            background: #f5f3ff;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
        }

        .dashboard-title h1 {
            margin: 0 0 8px;
            font-size: 30px;
            color: #222;
        }

        .dashboard-title p {
            margin: 0;
            color: #777;
            font-size: 15px;
        }

        .logout-btn {
            padding: 10px 18px;
            border-radius: 10px;
            background: white;
            color: #555;
            text-decoration: none;
            border: 1px solid #ddd;
            font-weight: 600;
            transition: 0.2s;
        }

        .logout-btn:hover {
            border-color: #7c3aed;
            color: #7c3aed;
        }


        /* ================================
           ACTION CARDS
        ================================= */

        .action-section {
            margin-bottom: 35px;
        }

        .section-title {
            font-size: 20px;
            margin: 0 0 15px;
            color: #333;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .action-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            border: 1px solid #eee;
            text-decoration: none;
            color: inherit;
            transition: 0.2s;
            display: block;
        }

        .action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.07);
            border-color: #ddd;
        }

        .action-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #f3e8ff;
            font-size: 22px;
            margin-bottom: 15px;
        }

        .action-card h3 {
            margin: 0 0 7px;
            font-size: 18px;
        }

        .action-card p {
            margin: 0;
            color: #777;
            font-size: 14px;
            line-height: 1.5;
        }


        /* ================================
           MEAL GROUPS
        ================================= */

        .groups-section {
            margin-bottom: 30px;
        }

        .groups-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .groups-count {
            color: #777;
            font-size: 14px;
        }

        .meal-groups {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .meal-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            border: 1px solid #eee;
        }

        .meal-card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
        }

        .meal-card h3 {
            margin: 0;
            font-size: 20px;
            color: #222;
        }

        .role-badge {
            padding: 5px 10px;
            border-radius: 20px;
            background: #f3e8ff;
            color: #7c3aed;
            font-size: 12px;
            font-weight: 700;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .meal-info {
            display: grid;
            gap: 10px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            font-size: 14px;
        }

        .info-label {
            color: #888;
        }

        .info-value {
            color: #444;
            font-weight: 600;
            text-align: right;
        }

        .join-code {
            font-family: monospace;
            letter-spacing: 1px;
        }

        .open-meal-btn {
            display: block;
            width: 100%;
            box-sizing: border-box;
            text-align: center;
            padding: 12px;
            border-radius: 10px;
            background: #7c3aed;
            color: white;
            text-decoration: none;
            font-weight: 600;
        }

        .open-meal-btn:hover {
            background: #6d28d9;
        }


        /* ================================
           EMPTY STATE
        ================================= */

        .empty-card {
            background: white;
            border-radius: 16px;
            padding: 45px 25px;
            text-align: center;
            border: 1px solid #eee;
        }

        .empty-icon {
            font-size: 45px;
            margin-bottom: 15px;
        }

        .empty-card h3 {
            margin: 0 0 8px;
        }

        .empty-card p {
            color: #777;
            font-size: 14px;
            margin: 0 0 20px;
        }

        .empty-actions {
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .empty-btn {
            padding: 11px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
        }

        .empty-btn.primary {
            background: #7c3aed;
            color: white;
        }

        .empty-btn.secondary {
            background: #f3e8ff;
            color: #7c3aed;
        }


        /* ================================
           RESPONSIVE
        ================================= */

        @media (max-width: 800px) {

            .action-grid,
            .meal-groups {
                grid-template-columns: 1fr;
            }

            .dashboard-header {
                align-items: flex-start;
                flex-direction: column;
            }

        }

        @media (max-width: 600px) {

            .dashboard-title h1 {
                font-size: 25px;
            }

            .meal-card,
            .action-card {
                padding: 20px;
            }

            .meal-card-top {
                flex-direction: column;
            }

            .role-badge {
                align-self: flex-start;
            }

        }

    </style>

</head>

<body>

<div class="dashboard-page">

    <main class="main-content">

        <!-- ================================
             HEADER
        ================================= -->

        <div class="dashboard-header">

            <div class="dashboard-title">

                <h1>
                    Welcome,
                    <?= htmlspecialchars($_SESSION["user_name"]) ?>! 👋
                </h1>

                <p>
                    Manage your meals and meal groups from here.
                </p>

            </div>

            <a
                href="../logout.php"
                class="logout-btn"
            >
                Logout
            </a>

        </div>


        <!-- ================================
             MEAL ACTIONS
        ================================= -->

        <section class="action-section">

            <h2 class="section-title">
                Meal Actions
            </h2>

            <div class="action-grid">

                <a
                    href="../meal/create.php"
                    class="action-card"
                >

                    <div class="action-icon">
                        ➕
                    </div>

                    <h3>
                        Create New Meal
                    </h3>

                    <p>
                        Create a new meal group and become its manager.
                    </p>

                </a>


                <a
                    href="../meal/join.php"
                    class="action-card"
                >

                    <div class="action-icon">
                        🔗
                    </div>

                    <h3>
                        Join a Meal
                    </h3>

                    <p>
                        Join an existing meal group using a join code.
                    </p>

                </a>

            </div>

        </section>


        <!-- ================================
             MY MEAL GROUPS
        ================================= -->

        <section class="groups-section">

            <div class="groups-header">

                <h2 class="section-title">
                    My Meal Groups
                </h2>

                <span class="groups-count">
                    <?= count($meal_groups) ?>
                    <?= count($meal_groups) === 1 ? "group" : "groups" ?>
                </span>

            </div>


            <?php if (count($meal_groups) > 0): ?>

                <div class="meal-groups">

                    <?php foreach ($meal_groups as $meal): ?>

                        <div class="meal-card">

                            <div class="meal-card-top">

                                <h3>
                                    <?= htmlspecialchars($meal["name"]) ?>
                                </h3>

                                <span class="role-badge">
                                    <?= htmlspecialchars(
                                        str_replace("_", " ", $meal["role"])
                                    ) ?>
                                </span>

                            </div>


                            <div class="meal-info">

                                <div class="info-row">

                                    <span class="info-label">
                                        Period
                                    </span>

                                    <span class="info-value">
                                        <?= htmlspecialchars($meal["month_name"]) ?>
                                        <?= htmlspecialchars($meal["year"]) ?>
                                    </span>

                                </div>


                                <div class="info-row">

                                    <span class="info-label">
                                        Join Code
                                    </span>

                                    <span class="info-value join-code">
                                        <?= htmlspecialchars($meal["join_code"]) ?>
                                    </span>

                                </div>

                            </div>


                            <a
                                href="meal.php?id=<?= $meal["id"] ?>"
                                class="open-meal-btn"
                            >
                                Open Meal
                            </a>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-card">

                    <div class="empty-icon">
                        🍽️
                    </div>

                    <h3>
                        No Meal Groups Yet
                    </h3>

                    <p>
                        Create a new meal group or join an existing one
                        to get started.
                    </p>

                    <div class="empty-actions">

                        <a
                            href="../meal/create.php"
                            class="empty-btn primary"
                        >
                            Create Meal
                        </a>

                        <a
                            href="../meal/join.php"
                            class="empty-btn secondary"
                        >
                            Join Meal
                        </a>

                    </div>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>

</html>