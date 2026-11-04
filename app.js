// Client-side helpers (the server re-validates everything; this is only for convenience).
document.addEventListener('click', function (e) {
  var el = e.target.closest('[data-confirm]');
  if (el && !window.confirm(el.getAttribute('data-confirm'))) e.preventDefault();
});

var signup = document.getElementById('signup');
if (signup) {
  signup.addEventListener('submit', function (e) {
    var f = signup.elements, msgs = [];
    if (!/^\d{7}$/.test(f.student_id.value)) msgs.push('Student ID must be 7 digits.');
    if (f.password.value.length < 8 || !/[A-Za-z]/.test(f.password.value) || !/\d/.test(f.password.value))
      msgs.push('Password needs 8+ characters with a letter and a number.');
    if (f.password.value !== f.password2.value) msgs.push('Passwords do not match.');
    if (msgs.length) { e.preventDefault(); window.alert(msgs.join('\n')); }
  });
}
