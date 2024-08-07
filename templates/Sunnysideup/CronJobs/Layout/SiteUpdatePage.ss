<% if $HasContent %>
    $Content
    <h2>Main Page</h2>
    <p><a href="$Link">Return to Main Page</a></p>
<% else %>

<h1>Intro</h1>
<div class="content">
    $Content
    $Form
    <p>
        Please also visit <a href='/admin/site-updates'>The Site Update Log</a>
        to review update logs.
    </p>
    <p>Please <a href="$CMSEditLink">add more instructions here...</a></p>
</div>

<h1>Currently Running</h1>
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
        Nothing is running.
    </p>
<% end_if %>





<h1>Run Now</h1>
<h2>Emergency Links</h2>
<% include MyChildLinks MyList=$EmergencyLinks %>
<p class="message warning">
Currently Product Update from Advance Retail are
<strong>
    <% if $AllowSiteUpdatesRightNow %>
        Allowed to Run.
    <% else %>
        Not Allowed to Run.
    <% end_if %>
    </strong>
    You can change this above.
</p>

<% if $AnalysisLinks %>
<h2>Analyses</h2>
<% include MyChildLinks MyList=$AnalysisLinks %>
<% end_if %>

<% if $RecipeLinks %>
<h2>Update Recipes</h2>
<ul>
    <% loop $RecipeLinks %>
    <li>
        <h3><% if $HasErrors %>❌<% else %>✓<% end_if %> $Title</h3>
        <p class="show-on-hover">
            $Description<br />
            <a href="$Link">▶ schedule now</a><br />
            <br /><strong>Hours of the day it runs:</strong> $HoursOfTheDayNice
            <br /><strong>Minimum number of minutes between runs:</strong> $MinMinutesBetweenRunsNice
            <br /><strong>Maximum number of minutes between runs:</strong> $MaxMinutesBetweenRunsNice
            <br /><strong>Number of Logs:</strong> $NumberOfLogs
            <br /><strong>Last Started:</strong> $LastStarted
            <br /><strong>Last Completed:</strong> $LastCompleted
            <br /><strong>Average Time Taken:</strong> $AverageTimeTaken
            <br /><strong>Average Memory Taken:</strong> $AverageMemoryTaken
            <br /><strong>Max Time Taken:</strong> $MaxTimeTaken
            <br /><strong>Max Memory Taken:</strong> $MaxMemoryTaken
        </p>
            <% if $SubLinks %>
            <ol>
            <% loop $SubLinks %>
                <li>
                <h4><% if $HasErrors %>❌<% else %>✓<% end_if %> $Title</h4>
                    <p class="show-on-hover">
                        <a href="$Link">▶ schedule now</a>
                        <br /><strong>Number of Logs:</strong> $NumberOfLogs
                        <br /><strong>Last Started:</strong> $LastStarted
                        <br /><strong>Last Completed:</strong> $LastCompleted
                        <br /><strong>Average Time Taken:</strong> $AverageTimeTaken
                        <br /><strong>Average Memory Taken:</strong> $AverageMemoryTaken
                        <br /><strong>Max Time Taken:</strong> $MaxTimeTaken
                        <br /><strong>Max Memory Taken:</strong> $MaxMemoryTaken
                        <br />$getDescription
                    </p>
                </li>
            <% end_loop %>
            </ol>
            <% end_if %>
    </li>
<% end_loop %>
</ul>
<% end_if %>
<% end_if %>
