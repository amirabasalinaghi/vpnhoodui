function getToken(id){
    event.preventDefault()
    if(document.getElementById(id).classList.contains('d-none')){
        getTokenFromServer(id)
    }else{
        document.getElementById(id).classList.add("d-none")
        document.getElementById(id+'_cpbtn').classList.add("d-none")
        if(document.getElementById(id+'_qr')){
            document.getElementById(id+'_qr').classList.add("d-none")
            document.getElementById(id+'_qr').removeAttribute('src')
        }
    }
}

function generateToken(id){
    event.preventDefault()
    if(document.getElementById(id).classList.contains('d-none')){
        getTokenFromServer(id, true)
    }else{
        document.getElementById(id).classList.add("d-none")
        document.getElementById(id+'_cpbtn').classList.add("d-none")
        if(document.getElementById(id+'_qr')){
            document.getElementById(id+'_qr').classList.add("d-none")
            document.getElementById(id+'_qr').removeAttribute('src')
        }
    }
}

function deleteToken(id){
    event.preventDefault()
    if(confirm('Delete this token?')){
        deleteTokenFromServer(id)
    }
}
function deleteTokenFromServer(id) {
    let url = baseUrlAll+'?delete=' + id
    fetch(url)
        .then(responce => responce.text())
        .then((x)=> {
            alert('Token Deleted. Refreshing...')
            window.location.reload();
        })
        .catch((e) => {
            document.getElementById(id+'_spinner').classList.add("d-none")
            console.log(e)
        })
}

function getTokenFromServer(id, gen=false){
    let url = baseUrlAll+'?printtoken='+id
    if (gen){
        let tokenName = document.getElementById('tokenName').value
        let expire = document.getElementById('expire').value
        url = baseUrlAll+'?gen=1&tokenName='+encodeURIComponent(tokenName)+'&expire='+encodeURIComponent(expire)
    }
    document.getElementById(id+'_spinner').classList.remove("d-none")

    fetch(url)
        .then(responce => responce.text())
        .then(token => showTokenBox(id,token))
        .catch((e) => {
            document.getElementById(id+'_spinner').classList.add("d-none")
            console.log(e)
        })

}

function showTokenBox(id,token){
    document.getElementById(id).innerText = token
    document.getElementById(id+'_spinner').classList.add("d-none")
    document.getElementById(id).classList.remove("d-none")
    document.getElementById(id+'_cpbtn').classList.remove("d-none")
    if(document.getElementById(id+'_qr')){
        document.getElementById(id+'_qr').src = baseUrlAll+'?qrcode='+id
        document.getElementById(id+'_qr').classList.remove('d-none')
    }
}

function openShareModal(id){
    document.getElementById('shareModalQr').src = baseUrlAll+'?shareqr='+id
    document.getElementById('shareLinkInput').value = baseUrlAll+'?share='+id
    var modal = new bootstrap.Modal(document.getElementById('shareModal'))
    modal.show()
}

function copyText(id) {
    // Get the text field
    var copyText = document.getElementById(id);

    // Copy the text inside the text field
    if (!navigator.clipboard || !navigator.clipboard.writeText){
        unsecuredCopyToClipboard(copyText.innerText);
        alert("Copied the text: " + copyText.innerText);
        window.location.reload();
        return;
    }
    navigator.clipboard.writeText(copyText.innerText)
        .then(function(){
            alert("Copied the text: " + copyText.innerText);
            window.location.reload();
        })
        .catch(function(){
            alert("Clipboard access was denied. Please copy the token manually.");
        });

}

function unsecuredCopyToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
        document.execCommand('copy');
    } catch (err) {
        console.error('Unable to copy to clipboard', err);
    }
    document.body.removeChild(textArea);
}

function filterTokens(){
    var value = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.token-card').forEach(function(card){
        var title = card.querySelector('.card-title').innerText.toLowerCase();
        if(title.includes(value)){
            card.classList.remove('d-none');
        }else{
            card.classList.add('d-none');
        }
    });
}
