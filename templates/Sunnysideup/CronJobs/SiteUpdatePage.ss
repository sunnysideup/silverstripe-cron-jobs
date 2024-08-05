<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
    <head>
        <% base_tag %>
        <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
        <title>$Subject</title>
        <style>
            body {
                font-family: sans-serif;
            }
            h1, h2, h3, h4 {
                font-family: serif;
            }
            h1 {
                font-size: 45px;
            }
            #Wrapper {
                margin: 0 auto;
                max-width: 1200px;
                padding-left: calc(240px + 10vw);
            }
            .logo {
                display: block;
                margin: 20px auto;
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
            li > .show-on-hover {
                /* display: none; */
                font-size: 12px;
                color: grey;
            }
            li:hover li > .show-on-hover,
            li:hover > .show-on-hover {
                /* display: block; */
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
            }

            #content ul,
            #content ol {
                list-style: none;
                counter-reset: my-awesome-counter;
            }
            #content ol li {
                counter-increment: my-awesome-counter;
            }
            #content ol li::before {
              content: counter(my-awesome-counter);
              color: #fcd000;
              float: left;
            }
            h4 {
                padding: 0;
                margin: 0;
                margin-left: 1em;
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
                width: calc(300px + 10vw);
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

        </style>
    </head>
    <body>
        <div id="Wrapper">
            <div id="toc"></div>
            <div class="typography" id="content">
                $Layout
                $Form
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
