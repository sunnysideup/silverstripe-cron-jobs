<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
    <head>
        <% base_tag %>
        <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
        <title>$Subject</title>
        <style>
            body, * {
                font-family: sans-serif;
            }
            h1 {
                font-size: 45px;
            }
            #Wrapper {
                margin: 0 auto;
                max-width: 1200px;
                padding-left: calc(260px + 10vw);
            }

            td {
                padding: 5px;
            }
            li {
                padding-top: 5px;
            }
            ul.with-bullets, ul.with-bullets li {
                list-style: circle;
                padding-bottom: 5px;
            }
            #content h1 {
                color: navy;
                border-top: 2px solid navy;
                padding: 2rem 0 0 0;
                font-size: 40px;
            }
            #content h2 {
                color: navy;
                border-top: 2px solid navy;
                padding: 2rem 0 0 0;
                font-size: 30px;
            }
            #content h3 {
                border-top: 1px solid #ccc;
                padding-top: 20px;
                padding-bottom: 0;
                margin-bottom: 0;
            }
            #content h4 {
                font-size: 17px;
                padding-top: 20px;
                padding-bottom: 0;
                margin-bottom: 0;

            }

            td {
                border: 1px solid #ccc;
            }
            th {
                border: 1px solid #ccc;
                background-color: #ccc;
            }
            #toc {
                padding-left: 7px;
                position: fixed;
                top: 0;
                bottom: 0;
                background-color: navy;
                left: 0;
                width:  calc(220px + 10vw);
                overflow-y: auto;
            }
            #toc ol,
            #toc li {
                margin-left: 0px;
                list-style: none;
            }
            #toc li a {
                display: block;
            }
            #toc li:hover > a {
                background-color: blue;
            }
            #toc li {
                list-style: none;
                padding: 7px 0;
                border-bottom: 1px solid #fff;
                font-size: 20px;
            }
            #toc li ol {
                padding-top: 0px;
            }
            #toc li ol,
            #toc li li {
                margin-left: 0px;
                font-size: 15px;
            }
            #toc a {
                color: #fff;
                text-decoration: none;
            }
            #toc ol {
                padding-left: 12px;
            }
            .button {
                background-color: #007bff; /* Primary color */
                color: white;              /* Text color */
                padding: 10px 20px;        /* Padding around text */
                border: none;              /* Remove default border */
                border-radius: 5px;        /* Rounded corners */
                cursor: pointer;           /* Pointer cursor on hover */
                font-size: 16px;           /* Font size */
                font-weight: bold;         /* Bold text */
                transition: background-color 0.3s ease, transform 0.2s ease; /* Smooth transition */
                text-align: center;        /* Center text */
                display: inline-block;     /* Inline-block for proper sizing */
                text-decoration: none;     /* Remove underline for links */
            }

            /* Hover state */
            .button:hover {
                background-color: #0056b3; /* Darker shade on hover */
            }

            /* Active state */
            .button:active {
                background-color: #004494; /* Even darker on active press */
                transform: translateY(2px); /* Slightly moves the button down */
            }

            /* Disabled state */
            .button:disabled {
                background-color: #cccccc; /* Gray background */
                cursor: not-allowed;       /* Not-allowed cursor */
            }
            .boolean-nice-and-colourfull {
                display: inline-block;
                padding: 3px;
            }
            .show-on-hover .stat-item {
                flex-direction: row;
                flex-wrap: nowrap;
                justify-content: space-between;
                display: none;
                padding: 5px 0;
                max-width: 900px;
            }
            .show-on-hover .stat-item span {
                text-align: right;
            }
            .show-on-hover:hover .stat-item {
                display: flex;
            }
        </style>
    </head>
    <body>
        <div id="Wrapper">
            <div id="toc"></div>
            <div class="typography" id="content">

<% if $HasContent %>
    <h2>Sub Page</h2>
    $Content.RAW
    <p><a href="$Link">Return to Main Page</a></p>
<% else %>
    <h2>Main Page</h2>

<h2>Intro</h2>
<div class="content">
    <p>
        Please also visit <a href='/admin/site-updates'>The Site Update Log</a>
        to review update logs.
    </p>
</div>

<% include CurrentlyRunning %>
<% include RunnningNext %>

<p class="message warning">
Currently Site Updates are
<strong>
    <% if $AllowSiteUpdatesRightNow %>
        allowed
    <% else %>
        not allowed
    <% end_if %>
</strong>
    to run.
    You can change this below.
</p>




<h2>Emergency Links</h2>
<% include MyChildLinks MyList=$EmergencyLinks %>

<% if $AnalysisLinks %>
<h2>Analyses</h2>
<% include MyChildLinks MyList=$AnalysisLinks %>
<% end_if %>

<% if $RecipeLinks %>
<h2>Update Recipes</h2>
<ul>
    <% loop $RecipeLinks %>
    <li>
        <div class="show-on-hover">
            <h3>$LastRunHadErrorsSymbol $Title</h3>
            <% if $Description %><p>$Description</p><% end_if %>
            <div class="stat-item"><strong>Minimum number of minutes between runs:</strong> <span>$MinMinutesBetweenRunsNice</span></div>
            <div class="stat-item"><strong>Maximum number of minutes between runs:</strong> <span>$MaxMinutesBetweenRunsNice</span></div>
            <div class="stat-item"><a href="$Link" class="button">▶ schedule now</a></div>
            <div class="stat-item"><strong>Has had Errors:</strong> <span>$HasHadErrorsNice</span></div>
            <div class="stat-item"><strong>Can Run:</strong> <span>$CanRunNice</span></div>
            <div class="stat-item"><strong>Number of Logs:</strong> <span>$NumberOfLogs</span></div>
            <div class="stat-item"><strong>Last Started:</strong> <span>$LastStarted</span></div>
            <div class="stat-item"><strong>Last Completed:</strong> <span>$LastCompleted</span></div>
            <div class="stat-item"><strong>Average Time Taken:</strong> <span>$AverageTimeTaken seconds</span></div>
            <div class="stat-item"><strong>Max Time Taken:</strong> <span>$MaxTimeTaken seconds</span></div>
            <div class="stat-item"><strong>Average Memory Taken:</strong> <span>$AverageMemoryTaken megabytes</span></div>
            <div class="stat-item"><strong>Max Memory Taken:</strong> <span>$MaxMemoryTaken megabytes</span></div>
        </div>
            <% if $SubLinks %>
            <ol>
            <% loop $SubLinks %>
                <li>
                    <div class="show-on-hover">
                        <h4>$LastRunHadErrorsSymbol $Title</h4>
                        <% if $Description %><p>$Description</p><% end_if %>
                        <div class="stat-item"><a href="$Link" class="button">▶ schedule now</a></div>
                        <div class="stat-item"><strong>Has had Errors:</strong> <span>$HasHadErrorsNice</span></div>
                        <div class="stat-item"><strong>Can Run:</strong> <span>$CanRunNice</span></div>
                        <div class="stat-item"><strong>Number of Logs:</strong> <span>$NumberOfLogs</span></div>
                        <div class="stat-item"><strong>Last Started:</strong> <span>$LastStarted</span></div>
                        <div class="stat-item"><strong>Last Completed:</strong> <span>$LastCompleted</span></div>
                        <div class="stat-item"><strong>Average Time Taken:</strong> <span>$AverageTimeTaken seconds</span></div>
                        <div class="stat-item"><strong>Max Time Taken:</strong> <span>$MaxTimeTaken seconds</span></div>
                        <div class="stat-item"><strong>Average Memory Taken:</strong> <span>$AverageMemoryTaken megabytes</span></div>
                        <div class="stat-item"><strong>Max Memory Taken:</strong> <span>$MaxMemoryTaken megabytes</span></div>
                    </div>
                </li>
            <% end_loop %>
            </ol>
            <% end_if %>
    </li>
<% end_loop %>
</ul>
<% end_if %>
<% end_if %>










            </div>

        </div>
<script>
// prepare the array by adding level, ID and parent to each item of the array
function prepare (array) {
  let level, t
  for (let i = 0, n = array.length; i < n; i++) {
    t = array[i]
    t.el = t
    level = parseInt(t.tagName[1], 10)
    t.level = level
    t.idt = i + 1

    if (level <= 1) t.parent = 0
    if (i) {
      if (array[i - 1].level < level) {
        t.parent = array[i - 1].idt
      } else if (array[i - 1].level === level) {
        t.parent = array[i - 1].parent
      } else {
        for (let j = i - 1; j >= 0; j--) {
          if (array[j].level === level - 1) {
            t.parent = array[j].idt
            break
          }
        }
      }
    }
  }
  return array
}

// transform a flat array in a hierarchical array
function hierarchical (items) {
  const hashTable = Object.create(null)
  items.forEach(item => hashTable[item.idt] = { ...item, subitems: [] })
  const tree = []
  items.forEach(item => {
    if (item.parent) {
      hashTable[item.parent].subitems.push(hashTable[item.idt])
    } else {
      tree.push(hashTable[item.idt])
    }
  })
  return tree
}

// return an UL containing each title in a LI and possibly other items in UL sub-lists.
function addList (titles) {
  let li, a, anchor
  // let base = document.querySelector('head base')['href']+'admin-update/';
  const base = document.location.href.split('#')[0]
  const ol = document.createElement('ol')
  if (titles && titles.length) {
    let t
    for (t of titles) {
      if (t.el.id) anchor = t.el.id
      else anchor = t.el.textContent
      if (!anchor) anchor = 'inconnu'
      anchor = anchor.replace(/\W/g, '')
      t.el.id = anchor
      li = document.createElement('li')
      a = document.createElement('a')
      a.href = base + `#${anchor}`
      a.innerHTML = t.el.textContent
      li.append(a)
      if (t.subitems && t.subitems.length) {
        li.append(addList(t.subitems))
      }
      ol.append(li)
    }
  }
  return ol
}

// get the toc element
const divtoc = document.getElementById('toc')

// get the article element
const article = document.getElementById('content')

if (divtoc && article) {
  let titles = article.querySelectorAll('h1, h2, h3')
  titles = prepare(titles)
  titles = hierarchical(titles)
  const olRacine = addList(titles)
  divtoc.append(olRacine)
}

</script>


    </body>
</html>
