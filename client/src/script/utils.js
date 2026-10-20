function setHeaderTextColor(){
    const header = document.querySelector("#header");
    const logo = document.querySelector("#header-logo");
    const hero = document.querySelector("#hero");

    const path = "./public/images/logo/";

    if (!header || !hero) return;
    
    const observer = new IntersectionObserver((entries)=>{
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                header.classList.remove("header-dark");
                logo.src = path + "light.svg";
            }
            else {
                header.classList.add("header-dark");
                logo.src = path + "dark.svg";
            }
        });
    },{
        threshold: 0.15
    });

    observer.observe(hero);
}

setHeaderTextColor();

function loadMap(){
    const lat = -22.7505776;
    const lon = -47.6529226;

    const map = L.map("map", {
        dragging: false,
        scrollWheelZoom: false,
        touchZoom: false
    }).setView([lat, lon], 15);

    L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    const marker = L.marker([lat, lon]).addTo(map);
    marker.on("click", function () {
        window.open("https://maps.app.goo.gl/MnVEBLTFdu9kScCX6","_blank");
    });
}

loadMap();