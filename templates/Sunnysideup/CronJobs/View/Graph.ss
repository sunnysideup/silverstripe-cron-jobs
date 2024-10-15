<div class="cron-job-graph">
    <div class="cron-job-graph-title">$Title</div>
    <div style="height: {$Height}px; " id="$ID" class="cron-job-graph-graph">
    <% loop $Instructions %>
        <div style="top: {$Top}%; left: {$Left}%;  width: {$Width}%; height: {$Height}%;" class="$Class"<% if $Title %> data-tooltip="$Title"<% end_if %>>
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
        background-color: #fff;
        border-radius: 5px;
    }
    .cron-job-graph-graph {
        position: relative;
        overflow-x: visible;
    }
    .cron-job-graph-graph .cron-job-graph-title {
        font-weight: bold;
        height: auto!important;
    }
    .cron-job-graph-graph > div {
        min-width: 2px!important;
        position: absolute;
    }
    .cron-job-graph-graph .cron-job-graph-good {
        background-color: var(--success, #008a00);
    }
    .cron-job-graph-graph .cron-job-graph-bad {
        background-color: var(--danger, #da273b;);
    }

    .cron-job-graph-graph .cron-job-graph-content {
        background-color: #fff;
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
    .cron-job-graph-item {
      cursor: pointer;
    }

    .cron-job-graph-item::after {
      content: attr(data-tooltip);
      position: absolute;
      background-color: black;
      color: white;
      padding: 5px 10px;
      border-radius: 5px;
      white-space: nowrap;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s;
      top: 100%;  /* Position below the element */
      left: 50%;
      transform: translateX(-50%);
      z-index: 1000;
    }

    .cron-job-graph-item:hover::after {
      opacity: 1;
      visibility: visible;
    }


</style>

