<!-- Security Logs Page -->
<?php
$page = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$logs = $module->getSecurityLog($limit, $offset);

// Get event type filter
$filterType = isset($_REQUEST['filter_type']) ? $_REQUEST['filter_type'] : '';
?>

<div class="container-fluid">
    <h1><?php echo _("Security Audit Logs"); ?></h1>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs" role="tablist">
        <li role="presentation">
            <a href="?display=samlauth&view=main"><?php echo _("Identity Providers"); ?></a>
        </li>
        <li role="presentation">
            <a href="?display=samlauth&view=settings"><?php echo _("Settings"); ?></a>
        </li>
        <li role="presentation">
            <a href="?display=samlauth&view=certificates"><?php echo _("Certificates"); ?></a>
        </li>
        <li role="presentation" class="active">
            <a href="?display=samlauth&view=logs"><?php echo _("Security Logs"); ?></a>
        </li>
    </ul>

    <div class="tab-content" style="padding: 20px 0;">

        <!-- Filter and Search -->
        <div class="panel panel-default">
            <div class="panel-body">
                <form method="GET" action="?display=samlauth&view=logs" class="form-inline">
                    <input type="hidden" name="display" value="samlauth">
                    <input type="hidden" name="view" value="logs">

                    <div class="form-group">
                        <label for="filter_type"><?php echo _("Event Type"); ?>:</label>
                        <select name="filter_type" id="filter_type" class="form-control">
                            <option value=""><?php echo _("All Events"); ?></option>
                            <option value="login_success" <?php echo $filterType === 'login_success' ? 'selected' : ''; ?>>
                                <?php echo _("Login Success"); ?>
                            </option>
                            <option value="login_failure" <?php echo $filterType === 'login_failure' ? 'selected' : ''; ?>>
                                <?php echo _("Login Failure"); ?>
                            </option>
                            <option value="logout" <?php echo $filterType === 'logout' ? 'selected' : ''; ?>>
                                <?php echo _("Logout"); ?>
                            </option>
                            <option value="replay_attempt" <?php echo $filterType === 'replay_attempt' ? 'selected' : ''; ?>>
                                <?php echo _("Replay Attempt"); ?>
                            </option>
                            <option value="invalid_signature" <?php echo $filterType === 'invalid_signature' ? 'selected' : ''; ?>>
                                <?php echo _("Invalid Signature"); ?>
                            </option>
                            <option value="certificate_expiring" <?php echo $filterType === 'certificate_expiring' ? 'selected' : ''; ?>>
                                <?php echo _("Certificate Expiring"); ?>
                            </option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-filter"></i> <?php echo _("Filter"); ?>
                    </button>

                    <a href="?display=samlauth&view=logs" class="btn btn-default">
                        <i class="fa fa-times"></i> <?php echo _("Clear"); ?>
                    </a>

                    <div class="pull-right">
                        <button type="button" class="btn btn-info" id="refresh-logs">
                            <i class="fa fa-refresh"></i> <?php echo _("Refresh"); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><?php echo _("Recent Security Events"); ?></h4>
            </div>
            <div class="panel-body" style="padding: 0;">
                <?php if (empty($logs)): ?>
                    <div class="alert alert-info" style="margin: 15px;">
                        <i class="fa fa-info-circle"></i>
                        <?php echo _("No security events logged yet."); ?>
                    </div>
                <?php else: ?>
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th><?php echo _("Timestamp"); ?></th>
                                <th><?php echo _("Event Type"); ?></th>
                                <th><?php echo _("Message"); ?></th>
                                <th><?php echo _("IP Address"); ?></th>
                                <th><?php echo _("Details"); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?php echo date('Y-m-d H:i:s', $log['created_at']); ?></small>
                                </td>
                                <td>
                                    <?php
                                    $eventClass = 'default';
                                    $eventIcon = 'info-circle';

                                    switch ($log['event_type']) {
                                        case 'login_success':
                                            $eventClass = 'success';
                                            $eventIcon = 'check-circle';
                                            break;
                                        case 'login_failure':
                                            $eventClass = 'warning';
                                            $eventIcon = 'exclamation-triangle';
                                            break;
                                        case 'logout':
                                            $eventClass = 'info';
                                            $eventIcon = 'sign-out';
                                            break;
                                        case 'replay_attempt':
                                        case 'invalid_signature':
                                            $eventClass = 'danger';
                                            $eventIcon = 'ban';
                                            break;
                                        case 'certificate_expiring':
                                        case 'certificate_expired':
                                            $eventClass = 'warning';
                                            $eventIcon = 'certificate';
                                            break;
                                    }
                                    ?>
                                    <span class="label label-<?php echo $eventClass; ?>">
                                        <i class="fa fa-<?php echo $eventIcon; ?>"></i>
                                        <?php echo htmlspecialchars($log['event_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($log['message']); ?></td>
                                <td>
                                    <code><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></code>
                                </td>
                                <td>
                                    <?php if (!empty($log['details'])): ?>
                                        <button type="button" class="btn btn-xs btn-default view-details"
                                                data-details="<?php echo htmlspecialchars($log['details']); ?>">
                                            <i class="fa fa-eye"></i> <?php echo _("View"); ?>
                                        </button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="panel-footer">
                        <nav aria-label="Page navigation">
                            <ul class="pagination" style="margin: 0;">
                                <?php if ($page > 1): ?>
                                <li>
                                    <a href="?display=samlauth&view=logs&page=<?php echo ($page - 1); ?><?php echo $filterType ? '&filter_type=' . $filterType : ''; ?>"
                                       aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>

                                <li class="active"><a href="#"><?php echo _("Page"); ?> <?php echo $page; ?></a></li>

                                <?php if (count($logs) >= $limit): ?>
                                <li>
                                    <a href="?display=samlauth&view=logs&page=<?php echo ($page + 1); ?><?php echo $filterType ? '&filter_type=' . $filterType : ''; ?>"
                                       aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Log Statistics -->
        <div class="panel panel-info">
            <div class="panel-heading">
                <h4><?php echo _("Recent Activity Summary"); ?></h4>
            </div>
            <div class="panel-body">
                <?php
                // Get statistics for last 24 hours
                $sql = "SELECT event_type, COUNT(*) as count
                        FROM saml_security_log
                        WHERE created_at > ?
                        GROUP BY event_type
                        ORDER BY count DESC";
                $stmt = $module->db->prepare($sql);
                $stmt->execute(array(time() - 86400));
                $stats = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                ?>

                <?php if (empty($stats)): ?>
                    <p><?php echo _("No activity in the last 24 hours."); ?></p>
                <?php else: ?>
                    <h5><?php echo _("Last 24 Hours"); ?></h5>
                    <table class="table table-condensed">
                        <thead>
                            <tr>
                                <th><?php echo _("Event Type"); ?></th>
                                <th><?php echo _("Count"); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats as $stat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat['event_type']); ?></td>
                                <td><strong><?php echo $stat['count']; ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Security Tips -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><?php echo _("Security Monitoring Tips"); ?></h4>
            </div>
            <div class="panel-body">
                <ul>
                    <li>
                        <strong><?php echo _("Login Failures"); ?>:</strong>
                        <?php echo _("Multiple failures from the same IP may indicate brute force attack"); ?>
                    </li>
                    <li>
                        <strong><?php echo _("Replay Attempts"); ?>:</strong>
                        <?php echo _("Should be extremely rare - investigate immediately if seen"); ?>
                    </li>
                    <li>
                        <strong><?php echo _("Invalid Signatures"); ?>:</strong>
                        <?php echo _("May indicate certificate issues or man-in-the-middle attack"); ?>
                    </li>
                    <li>
                        <strong><?php echo _("Certificate Warnings"); ?>:</strong>
                        <?php echo _("Act on these alerts promptly to avoid authentication outages"); ?>
                    </li>
                    <li>
                        <strong><?php echo _("Regular Review"); ?>:</strong>
                        <?php echo _("Review logs weekly to establish baseline and detect anomalies"); ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logDetailsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><?php echo _("Log Entry Details"); ?></h4>
            </div>
            <div class="modal-body">
                <pre id="logDetailsContent" style="max-height: 400px; overflow-y: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <?php echo _("Close"); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // View log details
    $('.view-details').on('click', function() {
        var details = $(this).data('details');
        try {
            var formatted = JSON.stringify(JSON.parse(details), null, 2);
            $('#logDetailsContent').text(formatted);
        } catch(e) {
            $('#logDetailsContent').text(details);
        }
        $('#logDetailsModal').modal('show');
    });

    // Refresh logs
    $('#refresh-logs').on('click', function() {
        location.reload();
    });
});
</script>
