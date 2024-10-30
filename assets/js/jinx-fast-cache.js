document.querySelectorAll('span.jinx-fast-cache-inject[data-id][data-path]').forEach(element => {
  
  fetch(jinx_fast_cache.ajax_url+'?'+new URLSearchParams({
    action: 'jinx-fast-cache-inject',
    path: element.dataset.path,
    id: element.dataset.id
  }).toString(), {
    method: 'GET'
  }).then(response => {
    return response.text();
  }).then(html => {
    element.innerHTML = html;  
    element.dispatchEvent(new Event('jinx-fast-cache-inject'));
  }); 
  
});