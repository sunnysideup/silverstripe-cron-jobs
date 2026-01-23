
<h2>Currently Running</h2>
<% if $CurrentlyRunning %>

<ul>

<% loop $CurrentlyRunning %>
    <li>
    <% if $Group = 'Step' %> - <% end_if %><a href="$CMSEditLink">$Title ($getGroup)</a>
    </li>
<% end_loop %>
</ul>
<% else %>
    <p>
        Nothing is running right now.
    </p>
<% end_if %>

<p>
    <a href='/admin/site-updates/Sunnysideup-CronJobs-Model-Logs-SiteUpdate'>Review full list of updates:</a>
</p>
