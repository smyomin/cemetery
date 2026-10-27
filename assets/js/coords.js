function parseCoords(s) {
  var n = '(-?\\d+(?:\\.\\d+)?)';
  var res = [
    new RegExp('^\\s*' + n + '\\s*,\\s*' + n + '\\s*$'),
    new RegExp('!3d' + n + '!4d' + n),
    new RegExp('@' + n + ',' + n),
    new RegExp('[?&](?:q|ll|query)=' + n + '(?:,|%2C)\\+?' + n),
    new RegExp('/search/' + n + ',\\+?' + n)
  ];
  for (var i = 0; i < res.length; i++) {
    var m = s.match(res[i]);
    if (m) { return [m[1], m[2]]; }
  }
  return null;
}
function attachCoords(latId, lngId) {
  var lat = document.getElementById(latId), lng = document.getElementById(lngId);
  lat.addEventListener('input', function () {
    var c = parseCoords(this.value);
    if (c) { lat.value = c[0]; lng.value = c[1]; }
  });
}