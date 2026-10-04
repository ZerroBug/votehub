<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireSuperAdmin();

$pageTitle = 'Dashboard';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

?>

<main class="main">

    <?php require_once __DIR__ . '/../includes/topbar.php'; ?>

    <section class="content">

        <?php showFlash(); ?>

        <!-- PAGE HEADER -->
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h1 class="page-title mb-1">
                    Super Admin Dashboard
                </h1>

                <p class="page-subtitle mb-0">
                    Manage all voting events from one place.
                </p>
            </div>

            <a href="<?= APP_URL ?>/admin/events/create.php" class="btn btn-primary">

                <i class="bi bi-plus-lg me-2"></i>
                Create Event

            </a>
        </div>


        <?php

        /*
        |--------------------------------------------------------------------------
        | PLATFORM STATISTICS
        |--------------------------------------------------------------------------
        */

        $totalEvents = (int) $pdo
            ->query("SELECT COUNT(*) FROM events")
            ->fetchColumn();

        $activeEvents = (int) $pdo
            ->query("
                SELECT COUNT(*)
                FROM events
                WHERE status = 'Active'
            ")
            ->fetchColumn();

        $totalCategories = (int) $pdo
            ->query("SELECT COUNT(*) FROM categories")
            ->fetchColumn();

        $totalContestants = (int) $pdo
            ->query("SELECT COUNT(*) FROM contestants")
            ->fetchColumn();

        $totalVotes = (int) $pdo
            ->query("
                SELECT COALESCE(SUM(vote_count), 0)
                FROM votes
            ")
            ->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | SUCCESSFUL GROSS REVENUE
        |--------------------------------------------------------------------------
        |
        | Only successful transactions are included.
        |
        */

        $totalRevenue = (float) $pdo
            ->query("
                SELECT COALESCE(SUM(amount), 0)
                FROM transactions
                WHERE status = 'Successful'
            ")
            ->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | ADMIN / VOTEHUB REVENUE
        |--------------------------------------------------------------------------
        |
        | The percentage is stored against each transaction.
        |
        */

        $totalAdminRevenue = (float) $pdo
            ->query("
                SELECT
                    COALESCE(
                        SUM(
                            amount * admin_revenue_percentage / 100
                        ),
                        0
                    )
                FROM transactions
                WHERE status = 'Successful'
            ")
            ->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | CLIENT EARNED
        |--------------------------------------------------------------------------
        |
        | Client receives the balance after the Admin/VoteHub share.
        |
        */

        $totalClientEarned = round(
            $totalRevenue - $totalAdminRevenue,
            2
        );


        /*
        |--------------------------------------------------------------------------
        | CLIENT CASH-OUTS
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | Paid:
        |   Money has actually been paid to the client.
        |
        | Requested / Approved:
        |   Money is reserved for an outstanding cash-out.
        |
        | Rejected / Cancelled:
        |   No deduction.
        |
        */


        // Amount already paid to clients
        $totalClientPaid = (float) $pdo
            ->query("
                SELECT
                    COALESCE(SUM(requested_amount), 0)
                FROM client_cashouts
                WHERE status = 'Paid'
            ")
            ->fetchColumn();


        // Amount currently reserved for pending cash-outs
        $totalClientPending = (float) $pdo
            ->query("
                SELECT
                    COALESCE(SUM(requested_amount), 0)
                FROM client_cashouts
                WHERE status IN ('Requested', 'Approved')
            ")
            ->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | CLIENT AVAILABLE BALANCE
        |--------------------------------------------------------------------------
        |
        | Client Available =
        |
        | Client Earned
        | - Paid Cash-outs
        | - Pending/Approved Cash-outs
        |
        */

        $totalClientAvailable = max(
            0,
            round(
                $totalClientEarned
                - $totalClientPaid
                - $totalClientPending,
                2
            )
        );

        ?>


        <!-- ============================================================= -->
        <!-- TOP STATISTICS -->
        <!-- ============================================================= -->

        <div class="row g-3 mb-4">

            <!-- TOTAL EVENTS -->
            <div class="col-xl-3 col-md-6">

                <div class="card-box stat">

                    <div class="stat-icon">
                        <i class="bi bi-calendar-event"></i>
                    </div>

                    <div class="stat-label">
                        Total Events
                    </div>

                    <div class="stat-value">
                        <?= number_format($totalEvents) ?>
                    </div>

                </div>

            </div>


            <!-- ACTIVE EVENTS -->
            <div class="col-xl-3 col-md-6">

                <div class="card-box stat">

                    <div class="stat-icon">
                        <i class="bi bi-broadcast"></i>
                    </div>

                    <div class="stat-label">
                        Active Events
                    </div>

                    <div class="stat-value">
                        <?= number_format($activeEvents) ?>
                    </div>

                </div>

            </div>


            <!-- CONTESTANTS -->
            <div class="col-xl-3 col-md-6">

                <div class="card-box stat">

                    <div class="stat-icon">
                        <i class="bi bi-people"></i>
                    </div>

                    <div class="stat-label">
                        Contestants
                    </div>

                    <div class="stat-value">
                        <?= number_format($totalContestants) ?>
                    </div>

                </div>

            </div>


            <!-- TOTAL VOTES -->
            <div class="col-xl-3 col-md-6">

                <div class="card-box stat">

                    <div class="stat-icon">
                        <i class="bi bi-check2-square"></i>
                    </div>

                    <div class="stat-label">
                        Total Votes
                    </div>

                    <div class="stat-value">
                        <?= number_format($totalVotes) ?>
                    </div>

                </div>

            </div>

        </div>


        <!-- ============================================================= -->
        <!-- REVENUE SUMMARY CARDS -->
        <!-- ============================================================= -->

        <div class="row g-3 mb-4">


            <!-- GROSS REVENUE -->
            <div class="col-xl-3 col-md-6">

                <div class="card-box">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="text-secondary small">
                                Gross Revenue
                            </div>

                            <h4 class="mb-0 mt-2">
                                GHS <?= number_format($totalRevenue, 2) ?>
                            </h4>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-cash-stack"></i>
                        </div>

                    </div>

                </div>

            </div>


            <!-- ADMIN REVENUE -->
            <div class="col-xl-3 col-md-6">

                <div class="card-box">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="text-secondary small">
                                Admin / VoteHub
                            </div>

                            <h4 class="mb-0 mt-2">
                                GHS <?= number_format($totalAdminRevenue, 2) ?>
                            </h4>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-building"></i>
                        </div>

                    </div>

                </div>

            </div>


            <!-- CLIENT EARNED -->
            <div class="col-xl-3 col-md-6">

                <div class="card-box">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="text-secondary small">
                                Client Earned
                            </div>

                            <h4 class="mb-0 mt-2">
                                GHS <?= number_format($totalClientEarned, 2) ?>
                            </h4>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-person-check"></i>
                        </div>

                    </div>

                </div>

            </div>


            <!-- CLIENT AVAILABLE -->
            <div class="col-xl-3 col-md-6">

                <div class="card-box">

                    <div class="d-flex justify-content-between align-items-center">

                        <div>

                            <div class="text-secondary small">
                                Client Available
                            </div>

                            <h4 class="mb-0 mt-2">
                                GHS <?= number_format($totalClientAvailable, 2) ?>
                            </h4>

                        </div>

                        <div class="stat-icon">
                            <i class="bi bi-wallet2"></i>
                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- ============================================================= -->
        <!-- EVENTS + PLATFORM SUMMARY -->
        <!-- ============================================================= -->

        <div class="row g-3">


            <!-- ========================================================= -->
            <!-- EVENTS -->
            <!-- ========================================================= -->

            <div class="col-xl-8">

                <div class="panel">

                    <div class="panel-head">

                        <h5>
                            Events
                        </h5>

                        <a href="events/" class="btn btn-sm btn-light border">

                            Manage Events

                        </a>

                    </div>


                    <div class="table-responsive">

                        <table class="table mb-0">

                            <thead>

                                <tr>

                                    <th>
                                        Event
                                    </th>

                                    <th>
                                        Dates
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th>
                                        Categories
                                    </th>

                                    <th>
                                        Contestants
                                    </th>

                                    <th>
                                        Revenue Split
                                    </th>

                                    <th>
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php

                                $q = $pdo->query("
                                    SELECT
                                        e.*,

                                        (
                                            SELECT COUNT(*)
                                            FROM categories c
                                            WHERE c.event_id = e.id
                                        ) AS categories_count,

                                        (
                                            SELECT COUNT(*)
                                            FROM contestants x
                                            WHERE x.event_id = e.id
                                        ) AS contestants_count

                                    FROM events e

                                    ORDER BY e.created_at DESC

                                    LIMIT 8
                                ");

                                foreach ($q as $e):

                                    $adminPercentage =
                                        isset($e['admin_revenue_percentage'])
                                            ? (float) $e['admin_revenue_percentage']
                                            : 30;

                                    $clientPercentage =
                                        max(
                                            0,
                                            100 - $adminPercentage
                                        );

                                ?>

                                <tr>


                                    <!-- EVENT -->
                                    <td>

                                        <strong>
                                            <?= e($e['name']) ?>
                                        </strong>

                                        <small class="d-block text-secondary">
                                            <?= e($e['event_code']) ?>
                                        </small>

                                    </td>


                                    <!-- DATES -->
                                    <td>

                                        <?= date(
                                            'd M Y',
                                            strtotime($e['start_date'])
                                        ) ?>

                                        <small class="d-block text-secondary">

                                            to

                                            <?= date(
                                                'd M Y',
                                                strtotime($e['end_date'])
                                            ) ?>

                                        </small>

                                    </td>


                                    <!-- STATUS -->
                                    <td>

                                        <span class="badge-soft badge-<?= strtolower(
                                            $e['status']
                                        ) ?>">

                                            <?= e($e['status']) ?>

                                        </span>

                                    </td>


                                    <!-- CATEGORIES -->
                                    <td>
                                        <?= (int) $e['categories_count'] ?>
                                    </td>


                                    <!-- CONTESTANTS -->
                                    <td>
                                        <?= (int) $e['contestants_count'] ?>
                                    </td>


                                    <!-- REVENUE SPLIT -->
                                    <td>

                                        <span class="fw-semibold">

                                            <?= number_format(
                                                $adminPercentage,
                                                2
                                            ) ?>%

                                        </span>

                                        /

                                        <span class="text-secondary">

                                            <?= number_format(
                                                $clientPercentage,
                                                2
                                            ) ?>%

                                        </span>

                                        <small class="d-block text-secondary">
                                            Admin / Client
                                        </small>

                                    </td>


                                    <!-- VIEW -->
                                    <td>

                                        <a href="events/view.php?id=<?= (int) $e['id'] ?>"
                                            class="action-btn d-inline-grid place-items-center">

                                            <i class="bi bi-arrow-right"></i>

                                        </a>

                                    </td>

                                </tr>

                                <?php endforeach; ?>


                                <?php if (!$totalEvents): ?>

                                <tr>

                                    <td colspan="7" class="text-center text-secondary py-5">

                                        No events yet.

                                        Create your first event.

                                    </td>

                                </tr>

                                <?php endif; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>


            <!-- ========================================================= -->
            <!-- PLATFORM SUMMARY -->
            <!-- ========================================================= -->

            <div class="col-xl-4">

                <div class="panel">

                    <div class="panel-head">

                        <h5>
                            Platform Summary
                        </h5>

                    </div>


                    <div class="panel-body">


                        <!-- CATEGORIES -->
                        <div class="d-flex justify-content-between border-bottom py-3">

                            <span class="text-secondary">
                                Categories
                            </span>

                            <strong>
                                <?= number_format($totalCategories) ?>
                            </strong>

                        </div>


                        <!-- CONTESTANTS -->
                        <div class="d-flex justify-content-between border-bottom py-3">

                            <span class="text-secondary">
                                Contestants
                            </span>

                            <strong>
                                <?= number_format($totalContestants) ?>
                            </strong>

                        </div>


                        <!-- VOTES -->
                        <div class="d-flex justify-content-between border-bottom py-3">

                            <span class="text-secondary">
                                Votes
                            </span>

                            <strong>
                                <?= number_format($totalVotes) ?>
                            </strong>

                        </div>


                        <!-- GROSS REVENUE -->
                        <div class="d-flex justify-content-between border-bottom py-3">

                            <span class="text-secondary">
                                Gross Revenue
                            </span>

                            <strong>
                                GHS <?= number_format(
                                    $totalRevenue,
                                    2
                                ) ?>
                            </strong>

                        </div>


                        <!-- ADMIN -->
                        <div class="d-flex justify-content-between border-bottom py-3">

                            <span class="text-secondary">
                                Admin / VoteHub
                            </span>

                            <strong>
                                GHS <?= number_format(
                                    $totalAdminRevenue,
                                    2
                                ) ?>
                            </strong>

                        </div>


                        <!-- CLIENT EARNED -->
                        <div class="d-flex justify-content-between border-bottom py-3">

                            <span class="text-secondary">
                                Client Earned
                            </span>

                            <strong>
                                GHS <?= number_format(
                                    $totalClientEarned,
                                    2
                                ) ?>
                            </strong>

                        </div>


                        <!-- CLIENT PAID -->
                        <div class="d-flex justify-content-between border-bottom py-3">

                            <span class="text-secondary">
                                Client Cashed Out
                            </span>

                            <strong class="text-success">

                                GHS <?= number_format(
                                    $totalClientPaid,
                                    2
                                ) ?>

                            </strong>

                        </div>


                        <!-- PENDING CASH-OUT -->
                        <div class="d-flex justify-content-between border-bottom py-3">

                            <span class="text-secondary">
                                Pending Cash-out
                            </span>

                            <strong class="text-warning">

                                GHS <?= number_format(
                                    $totalClientPending,
                                    2
                                ) ?>

                            </strong>

                        </div>


                        <!-- AVAILABLE -->
                        <div class="d-flex justify-content-between py-3">

                            <span class="fw-semibold">
                                Client Available
                            </span>

                            <strong class="text-primary fs-5">

                                GHS <?= number_format(
                                    $totalClientAvailable,
                                    2
                                ) ?>

                            </strong>

                        </div>


                        <!-- CASH-OUT LINK -->
                        <a href="<?= APP_URL ?>/admin/cashouts/" class="btn btn-outline-primary w-100 mb-2">

                            <i class="bi bi-wallet2 me-2"></i>

                            Manage Client Cash-outs

                        </a>


                        <!-- CREATE EVENT -->
                        <a href="events/create.php" class="btn btn-primary w-100">

                            <i class="bi bi-plus-lg me-2"></i>

                            Create New Event

                        </a>

                    </div>

                </div>

            </div>

        </div>

    </section>

</main>


<?php

require_once __DIR__ . '/../includes/footer.php';

?>