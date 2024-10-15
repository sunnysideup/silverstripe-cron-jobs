
<h2>Running Next</h2>
<% if $CustomRunNext %>
<p>
    <a href="/admin/site-updates/Sunnysideup-CronJobs-Model-Logs-Custom-SiteUpdateRunNext">
        <strong>$CustomRunNext</strong> (manually added).
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
