#include "../include/compression.h"
#include <fstream>
#include <iostream>
#include <algorithm>
#include <queue>
#include <unordered_map>
#include <bitset>
#include <filesystem>

namespace DropLocker {

// Constructor
Compressor::Compressor() {}

// Destructor  
Compressor::~Compressor() {}

// Main compression function
CompressionResult Compressor::compress(const std::string& inputFile, 
                                      const std::string& outputFile,
                                      CompressionType type) {
    CompressionResult result;
    result.originalSize = getFileSize(inputFile);
    
    if (result.originalSize == 0) {
        result.success = false;
        result.error = "Input file not found or empty";
        return result;
    }

    // Auto-detect best algorithm if requested
    if (type == CompressionType::AUTO) {
        type = detectBestAlgorithm(inputFile);
    }

    // Perform compression based on algorithm
    switch (type) {
        case CompressionType::HUFFMAN:
            result = huffmanCompress(inputFile, outputFile);
            break;
        case CompressionType::LZW:
            result = lzwCompress(inputFile, outputFile);
            break;
        default:
            result = huffmanCompress(inputFile, outputFile);
    }

    return result;
}

// Main decompression function
CompressionResult Compressor::decompress(const std::string& inputFile, 
                                        const std::string& outputFile) {
    // For this implementation, we'll use file extension to determine algorithm
    std::string ext = getFileExtension(inputFile);
    
    if (ext == ".huf") {
        return huffmanDecompress(inputFile, outputFile);
    } else if (ext == ".lzw") {
        return lzwDecompress(inputFile, outputFile);
    } else {
        CompressionResult result;
        result.success = false;
        result.error = "Unknown compression format";
        return result;
    }
}

// Detect best compression algorithm for file type
CompressionType Compressor::detectBestAlgorithm(const std::string& filename) {
    std::string ext = getFileExtension(filename);
    std::transform(ext.begin(), ext.end(), ext.begin(), ::tolower);
    
    // Text files work better with Huffman
    if (ext == ".txt" || ext == ".csv" || ext == ".json" || ext == ".xml" || 
        ext == ".html" || ext == ".css" || ext == ".js" || ext == ".py" ||
        ext == ".cpp" || ext == ".h" || ext == ".java") {
        return CompressionType::HUFFMAN;
    }
    
    // Binary files and documents work better with LZW
    return CompressionType::LZW;
}

// Huffman Compression Implementation
CompressionResult Compressor::huffmanCompress(const std::string& input, const std::string& output) {
    CompressionResult result;
    std::vector<char> data = readFile(input);
    
    if (data.empty()) {
        result.success = false;
        result.error = "Could not read input file";
        return result;
    }

    // Count character frequencies
    std::unordered_map<char, int> frequencies;
    for (char c : data) {
        frequencies[c]++;
    }

    // Build Huffman tree
    auto compare = [](const std::shared_ptr<HuffmanNode>& a, const std::shared_ptr<HuffmanNode>& b) {
        return a->frequency > b->frequency;
    };
    
    std::priority_queue<std::shared_ptr<HuffmanNode>, 
                       std::vector<std::shared_ptr<HuffmanNode>>, 
                       decltype(compare)> pq(compare);

    // Create leaf nodes
    for (auto& pair : frequencies) {
        pq.push(std::make_shared<HuffmanNode>(pair.first, pair.second));
    }

    // Build tree
    while (pq.size() > 1) {
        auto left = pq.top(); pq.pop();
        auto right = pq.top(); pq.pop();
        
        auto parent = std::make_shared<HuffmanNode>(left->frequency + right->frequency);
        parent->left = left;
        parent->right = right;
        
        pq.push(parent);
    }

    auto root = pq.top();

    // Generate codes
    std::unordered_map<char, std::string> codes;
    std::function<void(std::shared_ptr<HuffmanNode>, std::string)> generateCodes = 
        [&](std::shared_ptr<HuffmanNode> node, std::string code) {
            if (!node) return;
            
            if (!node->left && !node->right) {
                codes[node->character] = code.empty() ? "0" : code;
                return;
            }
            
            generateCodes(node->left, code + "0");
            generateCodes(node->right, code + "1");
        };

    generateCodes(root, "");

    // Encode data
    std::string encoded;
    for (char c : data) {
        encoded += codes[c];
    }

    // Write compressed file (simplified - in real implementation, would write tree + encoded bits)
    std::ofstream outFile(output + ".huf", std::ios::binary);
    if (!outFile) {
        result.success = false;
        result.error = "Could not create output file";
        return result;
    }

    // Write frequency table size
    size_t tableSize = frequencies.size();
    outFile.write(reinterpret_cast<const char*>(&tableSize), sizeof(tableSize));
    
    // Write frequency table
    for (const auto& pair : frequencies) {
        outFile.write(&pair.first, 1);
        outFile.write(reinterpret_cast<const char*>(&pair.second), sizeof(pair.second));
    }
    
    // Write encoded data length
    size_t encodedSize = encoded.size();
    outFile.write(reinterpret_cast<const char*>(&encodedSize), sizeof(encodedSize));
    
    // Write encoded data (convert bit string to bytes)
    for (size_t i = 0; i < encoded.size(); i += 8) {
        std::string byte = encoded.substr(i, 8);
        if (byte.size() < 8) {
            byte += std::string(8 - byte.size(), '0'); // Pad with zeros
        }
        char byteValue = static_cast<char>(std::bitset<8>(byte).to_ulong());
        outFile.write(&byteValue, 1);
    }
    
    outFile.close();

    result.success = true;
    result.originalSize = data.size();
    result.compressedSize = getFileSize(output + ".huf");
    result.compressionRatio = calculateCompressionRatio(result.originalSize, result.compressedSize);
    result.algorithm = "Huffman Coding";

    return result;
}

// LZW Compression Implementation
CompressionResult Compressor::lzwCompress(const std::string& input, const std::string& output) {
    CompressionResult result;
    std::vector<char> data = readFile(input);
    
    if (data.empty()) {
        result.success = false;
        result.error = "Could not read input file";
        return result;
    }

    LZWDictionary dict;
    dict.initialize();
    
    std::vector<int> compressed;
    std::string current(1, data[0]);
    
    for (size_t i = 1; i < data.size(); i++) {
        std::string next = current + data[i];
        
        if (dict.search(next) != -1) {
            current = next;
        } else {
            compressed.push_back(dict.search(current));
            dict.add(next);
            current = std::string(1, data[i]);
        }
    }
    
    compressed.push_back(dict.search(current));

    // Write compressed file
    std::ofstream outFile(output + ".lzw", std::ios::binary);
    if (!outFile) {
        result.success = false;
        result.error = "Could not create output file";
        return result;
    }
    
    size_t compressedSize = compressed.size();
    outFile.write(reinterpret_cast<const char*>(&compressedSize), sizeof(compressedSize));
    
    for (int code : compressed) {
        outFile.write(reinterpret_cast<const char*>(&code), sizeof(code));
    }
    
    outFile.close();

    result.success = true;
    result.originalSize = data.size();
    result.compressedSize = getFileSize(output + ".lzw");
    result.compressionRatio = calculateCompressionRatio(result.originalSize, result.compressedSize);
    result.algorithm = "LZW Algorithm";

    return result;
}

// Utility Functions
std::string Compressor::getFileExtension(const std::string& filename) {
    size_t pos = filename.find_last_of('.');
    if (pos != std::string::npos) {
        return filename.substr(pos);
    }
    return "";
}

double Compressor::calculateCompressionRatio(size_t original, size_t compressed) {
    if (original == 0) return 0.0;
    return (1.0 - static_cast<double>(compressed) / static_cast<double>(original)) * 100.0;
}

std::vector<char> Compressor::readFile(const std::string& filename) {
    std::ifstream file(filename, std::ios::binary | std::ios::ate);
    if (!file) return {};
    
    std::streamsize size = file.tellg();
    file.seekg(0, std::ios::beg);
    
    std::vector<char> buffer(size);
    file.read(buffer.data(), size);
    return buffer;
}

bool Compressor::writeFile(const std::string& filename, const std::vector<char>& data) {
    std::ofstream file(filename, std::ios::binary);
    if (!file) return false;
    
    file.write(data.data(), data.size());
    return file.good();
}

size_t Compressor::getFileSize(const std::string& filename) {
    try {
        return std::filesystem::file_size(filename);
    } catch (...) {
        return 0;
    }
}

// LZW Dictionary Implementation
LZWDictionary::LZWDictionary() : nextCode(256) {}

void LZWDictionary::initialize() {
    stringToCode.clear();
    codeToString.clear();
    nextCode = 256;
    
    // Initialize with single characters
    for (int i = 0; i < 256; i++) {
        std::string s(1, static_cast<char>(i));
        stringToCode[s] = i;
        codeToString[i] = s;
    }
}

int LZWDictionary::search(const std::string& str) {
    auto it = stringToCode.find(str);
    return (it != stringToCode.end()) ? it->second : -1;
}

void LZWDictionary::add(const std::string& str) {
    if (nextCode < 4096) { // Limit dictionary size
        stringToCode[str] = nextCode;
        codeToString[nextCode] = str;
        nextCode++;
    }
}

std::string LZWDictionary::getEntry(int code) {
    auto it = codeToString.find(code);
    return (it != codeToString.end()) ? it->second : "";
}

} // namespace DropLocker
