
<h2>Currently Running</h2>
<% if $CurrentlyRunning %>
<p>
    <a href='/admin/site-updates'>Review list of updates:</a>
</p>

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
