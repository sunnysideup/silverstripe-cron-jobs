<div class="cron-job-graph">
    <div class="cron-job-graph-title">$Title</div>
    <div class="cron-job-graph-content-inner">
        <div style="height: {$Height}px; width: 100%; position: relative;" id="$ID">
        <% loop $Instructions %>
            <div style="position: absolute; top: {$Top}%; left: {$Left}%;  width: {$Width}%; height: {$Height}%;" class="$Class">
                <% if $Content %>
                <div class="cron-job-graph-content">
                    $Content.RAW
                </div>
                <% end_if %>
            </div>
        <% end_loop %>
        </div>
    </div>
</div>
<style>
    .cron-job-graph {
        margin: 10px 0;
        padding: 10px;
        border: 1px solid #ccc;
        background-color: antiquewhite;
        border-radius: 5px;
    }
    .cron-job-graph-title {
        font-weight: bold;
    }
    .cron-job-graph-content,
    .cron-job-graph-content *,
    .cron-job-graph-content *:link,
    .cron-job-graph-content *:visited {
        font-size: 10px;
        color: #000!important;
    }
    .cron-job-graph-content {
        background-color: #ffffff55;
        padding: 2px;
        overflow: hidden;
    }
    .cron-job-graph-content a {
        text-decoration: underline;
    }
    .cron-job-graph-good {
        background-color: #5cb85c;
    }
    .cron-job-graph-bad {
        background-color: #d9534f;
    }


</style>

