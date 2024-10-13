
<h2>Expectecd to run next</h2>
<% if $CustomRunNext %>
<p>
    <a href="/admin/site-updates/Sunnysideup-CronJobs-Model-Logs-Custom-SiteUpdateRunNext">
        <strong>$CustomRunNext</strong> (manually loaded to run next).
    </a>
</p>
<% else %>

<% if $RunningNext %>
<p>
    $RunningNext (based on normal scheduling).
</p>
<% else %>
    <p>
        There is nothing to run right now.
    </p>
<% end_if %>

<% end_if %>
