const imageInput = document.getElementById('imageInput');
const uploadBtn = document.getElementById('uploadBtn');
const resultDiv = document.getElementById('result');

uploadBtn.addEventListener('click', () => {
  const file = imageInput.files[0];

  if (!file) {
    resultDiv.textContent = 'Please select an image.';
    return;
  }

  const formData = new FormData();
  formData.append('image', file);

  fetch('process_image.php', {
    method: 'POST',
    body: formData,
  })
    .then(response => response.text()) // Read raw text response
    .then(text => {
      try {
        const data = JSON.parse(text); // Try parsing as JSON
        if (data.error) {
          resultDiv.textContent = data.error; // Show error message
        } else {
          resultDiv.textContent = `Success: ${data.message}`;
        }
      } catch (error) {
        console.error('Error parsing JSON:', error);
        console.error('Raw response:', text);
        resultDiv.textContent = 'An error occurred while processing the response.';
      }
    })
    .catch(error => {
      console.error('Error:', error);
      resultDiv.textContent = 'A network error occurred.';
    });
});
