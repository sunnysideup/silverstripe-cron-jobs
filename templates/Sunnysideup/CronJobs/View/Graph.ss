<div class="cron-job-graph">
    <div class="cron-job-graph-title">$Title</div>
    <div style="height: {$Height}px; " id="$ID" class="cron-job-graph-graph">
    <% loop $Instructions %>
        <div style="position: absolute; top: {$Top}%; left: {$Left}%;  width: {$Width}%; height: {$Height}%;" class="$Class" title="$Title">
            <% if $Content %>
            <div class="cron-job-graph-content">
                $Content.RAW
            </div>
            <% end_if %>
        </div>
    <% end_loop %>
    </div>
</div>
<style>
    .cron-job-graph {
        margin: 10px 0;
        padding: 10px;
        border: 1px solid #ccc;
        background-color: var(--light: #eef0f4);
        border-radius: 5px;
    }
    .cron-job-graph-graph {
        width: 100%;
        position: relative;
        min-width: 1440px!important;
        overflow-x: auto;
    }
    .cron-job-graph-graph .cron-job-graph-title {
        font-weight: bold;
    }
    .cron-job-graph-graph > div {
        min-width: 1px!important;
    }
    .cron-job-graph-graph .cron-job-graph-good {
        background-color: var(--success, #008a00);
    }
    .cron-job-graph-graph .cron-job-graph-bad {
        background-color: var(--danger, #da273b;);
    }

    .cron-job-graph-graph .cron-job-graph-content {
        background-color: #ffffffcc;
        padding: 2px;
        overflow: hidden;
    }
    .cron-job-graph-graph .cron-job-graph-content,
    .cron-job-graph-graph .cron-job-graph-content *,
    .cron-job-graph-graph .cron-job-graph-content *:link,
    .cron-job-graph-graph .cron-job-graph-content *:visited {
        font-size: 10px;
        color: #000!important;
    }
    .cron-job-graph-graph .cron-job-graph-content a {
        text-decoration: underline;
    }



</style>

